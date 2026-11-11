<?php
/** Run only through wp eval-file in the disposable local Docker site. All HTTP intercepted. */
if (!defined('WP_CLI') || !WP_CLI || !str_contains(home_url(), 'localhost:8087')) { exit('Local test site only.'); }
use Y2KWebs\Lafise\{Config, Gateway, Payments, Store, Money, Secrets};

define('Y2K_LAFISE_PRIVATE', ['username' => 'fixture', 'password' => 'fixture-not-a-secret', 'merchant_pay_id' => 'fixture-key',
    'merchant_id' => 'FIXTUREM', 'terminal_id' => 'FIXTURET', 'type_request' => 'TEST', 'currency' => 'USD',
    'pilot_limit_minor' => 100, 'evidence' => ['USD' => ['three_ds' => 'SYNTHETIC-LOCAL-ONLY']]]);
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'localhost:8087';
add_filter('pre_wp_mail', '__return_true');
update_option('woocommerce_y2kwebs_lafise_settings', ['enabled' => 'yes', 'currency' => 'USD', 'mode' => 'pilot']);
update_option('woocommerce_currency', 'USD');
wp_set_current_user(1);
$user = wp_get_current_user();
$user->add_cap('y2k_lafise_test');
Payments::session();
$checks = 0;
$check = static function (bool $ok, string $name) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $name); } $checks++;
};
$key = openssl_pkey_new(['private_key_bits' => 2048]);
$pem = openssl_pkey_get_details($key)['key'];
$calls = [];
$mode = 'fingerprint';
$completed = 0;
$activeReference = null;
$submissionProofs = [];
add_action('woocommerce_payment_complete', static function () use (&$completed): void { $completed++; });
$id = '00000000-0000-4000-8000-000000000001';
$encode = static fn ($v) => rtrim(strtr(base64_encode(is_array($v) ? json_encode($v) : $v), '+/', '-_'), '=');
$http = static function ($pre, $args, $url) use (&$calls, &$mode, $pem, $id, $encode, &$activeReference, &$submissionProofs) {
    $operation = basename(parse_url($url, PHP_URL_PATH));
    $calls[] = $operation;
    if (!str_starts_with($url, 'https://lafise-cr.portalpos.com/Interfaces/')) {
        return new WP_Error('test_http_blocked', 'All external requests blocked.');
    }
    if ($operation === 'tokenex') {
        $body = ['access_token' => 'fixture-token', 'serverTransportKey' => base64_encode($pem)];
    } elseif ($operation === 'Sale') {
        if ($mode === 'timeout') { return new WP_Error('http_request_failed', 'SIMULATED: sensitive body must never be logged'); }
        $body = ['ResponseCode' => '3DS-PRC-001', 'PaymentData' => ['Amount' => '100', 'Currency_code' => 'USD',
            'Merchant_Id' => 'FIXTUREM', 'Terminal_Id' => 'FIXTURET', 'Ref_1' => null, 'Ref_3' => null],
            'ApiSecurity' => ['Message_signature' => hash('sha256', 'fixture-key' . '100' . 'FIXTUREM' . '0' . 'USD')],
            'ExtraFile' => ['threeDSMethodURL' => 'https://issuer.bank.com/method', 'threeDSMethodData' => $encode(['threeDSServerTransID' => $id])]];
        if (str_starts_with($mode, 'direct')) {
            $body['ResponseCode'] = '3DS-PRC-002';
            $body['ApiSecurity']['Message_signature'] = hash('sha256', 'fixture-key' . '3DS-PRC-002' . '100' . 'FIXTUREM' . '0' . 'USD');
            $body['ExtraFile'] = ['Action' => 'https://issuer.bank.com/acs', 'Creq' => $encode(['threeDSServerTransID' => $id]), 'ThreeDSSessionData' => $encode($id)];
            if (in_array($mode, ['direct-camel', 'direct-upper'], true)) {
                $names = $mode === 'direct-camel' ? ['action', 'creq', 'threeDSSessionData'] : ['ACTION', 'CREQ', 'THREEDSSESSIONDATA'];
                $body['ExtraFile'] = array_combine($names, array_values($body['ExtraFile']));
            }
            if ($mode === 'direct-setup-bad') { $body['ExtraFile']['Creq'] = 'NOT VALID'; }
        }
    } elseif ($operation === 'Process3DS') {
        if ($activeReference) {
            $persisted = (new Store())->find($activeReference);
            $submissionProofs[] = $persisted['state'] === 'processing' && ($persisted['data']['three_ds_context']['submitted'] ?? false) === true;
        }
        $request = json_decode($args['body'], true);
        if (in_array($mode, ['fingerprint-challenge', 'fingerprint-challenge-auth-failure'], true) && !empty($request['threeDSData'])) {
            $body = ['ResponseCode' => '3DS-PRC-002', 'ACTION' => 'https://issuer.bank.com/acs',
                'CREQ' => $encode(['threeDSServerTransID' => $id]), 'THREEDSSESSIONDATA' => $encode($id)];
        } elseif (in_array($mode, ['auth-failure-fingerprint', 'direct-auth-failure', 'fingerprint-challenge-auth-failure', 'direct-auth-conflict'], true)) {
            $body = ['ResponseCode' => '3DS-RES-003', 'ResponseMessage' => 'SYNTHETIC-PRIVATE-BODY', 'PAN' => 'SYNTHETIC-PRIVATE-BODY'];
            if ($mode === 'direct-auth-conflict') { $body['paymentinformation'] = ['AuthorizationCode' => 'CONFLICT']; }
        } elseif ($mode === 'direct-process-bad') {
            $body = ['ResponseCode' => '3DS-PRC-002', 'action' => 'https://issuer.bank.com/acs', 'creq' => 'NOT VALID',
                'threeDSSessionData' => $encode($id), 'Unrelated' => 'SYNTHETIC-PRIVATE-BODY'];
        } else {
            $body = ['ResponseCode' => '00', 'PaymentInformation' => ['Amount' => '1.00', 'Currency_code' => 'USD',
                'Merchant_Id' => 'FIXTUREM', 'Terminal_Id' => 'FIXTURET', 'AuthorizationCode' => 'TEST', 'Ref1' => 'BANKREF', 'Ref3' => '000001'],
                'ApiSecurity' => ['Message_signature' => hash('sha256', 'fixture-key' . 'BANKREF' . '00' . '1.00' . 'FIXTUREM' . '0' . 'USD')]];
            if (in_array($mode, ['direct-decline', 'direct-forged', 'direct-conflict', 'direct-unknown'], true)) {
                $code = $mode === 'direct-unknown' ? '96' : '59';
                $body['ResponseCode'] = $code;
                $body['PaymentInformation']['AuthorizationCode'] = $mode === 'direct-conflict' ? 'CONTRADICTORY' : '';
                $body['ApiSecurity']['Message_signature'] = $mode === 'direct-forged' ? str_repeat('0', 64)
                    : hash('sha256', 'fixture-key' . 'BANKREF' . $code . '1.00' . 'FIXTUREM' . '0' . 'USD');
            }
        }
    } elseif ($operation === 'StatusCheck') {
        if ($mode !== 'query-approved') { return new WP_Error('fixture_query_failed', 'SYNTHETIC-PRIVATE-BODY'); }
        $body = ['Status' => 'OK', 'ResponseCode' => '00', 'ExtraFile' => ['OrderId' => '000001', 'Terminal_Id' => 'FIXTURET', 'Currency' => 'USD', 'Amount' => '1.00']];
    } else { return new WP_Error('test_unexpected_request', 'Unexpected operation.'); }
    return ['response' => ['code' => 200, 'message' => 'OK'], 'headers' => [], 'body' => wp_json_encode($body), 'cookies' => []];
};
add_filter('pre_http_request', $http, 10, 3);
$orders = [];
$product = new WC_Product_Simple();
$product->set_name('Synthetic payment fixture'); $product->set_regular_price('1.00');
$product->set_manage_stock(true); $product->set_stock_quantity(10); $product->save();
$create = static function () use (&$orders, $product): WC_Order {
    $order = wc_create_order(); $order->set_payment_method('y2kwebs_lafise'); $order->set_currency('USD');
    $order->add_product($product, 1); $order->calculate_totals(); $order->save(); $orders[] = $order->get_id(); return $order;
};
$service = new Payments();
$input = static function () use ($service): array {
    WC()->session->set('y2k_key_at', 0);
    $public = $service->key(Payments::csrf());
    return ['y2k_card' => 'e30.e30.e30.e30.e30', 'y2k_ticket' => $public['ticket'], 'y2k_csrf' => Payments::csrf()];
};
try {
    $check((new Config())->allowed(), 'Optional test mode available to administrators');
    $registry = new Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry();
    do_action('woocommerce_blocks_payment_method_type_registration', $registry);
    $check($registry->is_registered('y2kwebs_lafise'), 'Blocks registration survives WooCommerce early bootstrap');
    $beforeHttp = count($calls);
    try { $service->key('bad-csrf'); $check(false, 'CSRF'); } catch (DomainException) { $check(true, 'CSRF'); }
    $check(count($calls) === $beforeHttp, 'Invalid CSRF never reaches bank');
    try { (new Config())->guardAmount(101,0); $check(false,'Pilot limit'); } catch (DomainException) { $check(true,'Pilot limit'); }
    (new Config())->guardAmount(100,1); $check(true,'Taxes do not require certification flags');
    $user->remove_cap('y2k_lafise_test');
    wp_set_current_user(0);
    $check(!(new Config())->allowed(), 'Pilot excludes unauthorized user');
    wp_set_current_user(1);
    $user->add_cap('y2k_lafise_test');
    $order = $create();
    $request = $input();
    $transport = WC()->session->get('y2k_key');
    $a = $service->start($order, $request);
    $check($a['state'] === 'fingerprint', 'Initial 3DS');
    $check(wc_get_order($order->get_id())->get_status() === 'on-hold', 'Stock protected while awaiting bank');
    $before = count(array_filter($calls, fn ($c) => $c === 'Sale'));
    // Duplicate posts after the order is on hold cannot produce a second sale.
    try { $service->start(wc_get_order($order->get_id()), $request); } catch (Throwable) {}
    $check(count(array_filter($calls, fn ($c) => $c === 'Sale')) === $before, 'No duplicate Sale');
    WC()->session->set('y2k_key',$transport);
    $secondOrder = $create();
    global $wpdb;
    $previousErrors = $wpdb->suppress_errors(true);
    try { $service->start($secondOrder,$request); $check(false,'Consumed ticket'); } catch (RuntimeException) { $check(true,'Consumed ticket'); }
    $wpdb->suppress_errors($previousErrors);
    $check(count(array_filter($calls, fn ($c) => $c === 'Sale')) === $before, 'Cross-order ticket reuse never reaches bank');
    $bad = $encode(['threeDSServerTransID' => '00000000-0000-4000-8000-000000000002']);
    try { $service->callback($a['reference'], $a['data']['token'], ['threeDSMethodData' => $bad]); } catch (Throwable) {}
    $check(!in_array('Process3DS', $calls, true), 'Forged callback does not call bank');
    $callback = ['threeDSMethodData' => $encode(['threeDSServerTransID' => $id])];
    $service->callback($a['reference'], $a['data']['token'], $callback);
    $paid = wc_get_order($order->get_id());
    $check($paid->is_paid(), 'Signed payment completes order');
    $check($paid->get_transaction_id() === 'BANKREF', 'Bank reference saved');
    $check(wc_get_product($product->get_id())->get_stock_quantity() === 9, 'Stock reduced exactly once');
    $service->callback($a['reference'], $a['data']['token'], $callback);
    $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === 1, 'No duplicate Process3DS');
    $service->reconcile($a['reference']);
    $check(wc_get_product($product->get_id())->get_stock_quantity() === 9, 'Replay cannot reduce stock twice');
    try { $service->status($a['reference'], str_repeat('0',64)); $check(false,'Bearer token'); } catch (DomainException) { $check(true,'Bearer token'); }
    $check($service->status($a['reference'],$a['data']['token'])['redirect'] !== null,'Success redirect');
    $gateway = new Gateway();
    $check(!$gateway->supports('refunds'), 'No unsupported refund advertising');
    $check(is_wp_error($gateway->process_refund($order->get_id(), '1.00')), 'Refund fails closed without HTTP');
    $mode = 'timeout';
    $order = $create();
    $pending = $service->start($order,$input());
    $check($pending['state'] === 'unknown', 'Timeout is unknown');
    $check(($pending['data']['diagnostic']['failure'] ?? '') === 'transport_failed', 'Safe transport diagnostic persisted');
    $diagnosticUrl = '/y2k-lafise/v1/attempts/' . $pending['reference'];
    $server = rest_get_server();
    $diagnosticResponse = $server->dispatch(new WP_REST_Request('GET', $diagnosticUrl));
    $diagnosticData = $diagnosticResponse->get_data();
    $check($diagnosticResponse->get_status() === 200 && $diagnosticData['diagnostic']['stage'] === 'transport', 'Administrator can inspect safe diagnostics');
    $check(!array_key_exists('token', $diagnosticData) && !array_key_exists('session', $diagnosticData) && !array_key_exists('receipt', $diagnosticData)
        && !str_contains(wp_json_encode($diagnosticData), 'sensitive body'), 'Diagnostic API excludes private attempt data');
    wp_set_current_user(0);
    $deniedDiagnostic = $server->dispatch(new WP_REST_Request('GET', $diagnosticUrl));
    $check(in_array($deniedDiagnostic->get_status(), [401, 403], true), 'Guests cannot read attempt diagnostics');
    wp_set_current_user(1);
    $check(wc_get_order($order->get_id())->get_status() === 'on-hold', 'Unknown retained');
    $sales = count(array_filter($calls,fn($c)=>$c==='Sale'));
    $service->reconcile($pending['reference']);
    $check(count(array_filter($calls,fn($c)=>$c==='Sale')) === $sales,'Recovery never repeats Sale');
    $other = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    global $wpdb;
    $lock = substr('y2kl:' . hash('sha256',$wpdb->prefix.'y2k_lafise_attempts:'.$order->get_id()),0,64);
    $other->query("SELECT GET_LOCK('".$other->real_escape_string($lock)."',0)");
    try { (new Store())->locked($order->get_id(),fn()=>null); $check(false,'Concurrent lock'); } catch (RuntimeException) { $check(true,'Concurrent lock'); }
    $other->query("SELECT RELEASE_LOCK('".$other->real_escape_string($lock)."')"); $other->close();
    $sealed = Secrets::seal(['token'=>'synthetic']);
    $check(Secrets::open($sealed)['token']==='synthetic','Encrypted storage');
    $check(!str_contains($sealed,'synthetic'),'No cleartext storage');
    $check(!str_contains(Gateway::fields(),'name="'),'Card inputs excluded from serialization');
    $mode = 'fingerprint';
    $retryOrder = $create();
    $expired = $service->start($retryOrder,$input());
    $expired['data']['auth_expires'] = time()-1;
    (new Store())->save($expired,'fingerprint',$expired['data']);
    $beforeProcess = count(array_filter($calls,fn($c)=>$c==='Process3DS'));
    $state = $service->status($expired['reference'],$expired['data']['token']);
    $check($state['state']==='declined' && $state['retry'] !== null,'Expired authentication allows retry');
    $service->callback($expired['reference'],$expired['data']['token'],$callback);
    $check(count(array_filter($calls,fn($c)=>$c==='Process3DS'))===$beforeProcess,'Late callback cannot submit expired authentication');
    $retry = $service->start(wc_get_order($retryOrder->get_id()),$input());
    $check($retry['reference']!==$expired['reference'] && $retry['state']==='fingerprint','Retry uses new attempt in same order');

    $challengeCallback = ['cres' => $encode(['threeDSServerTransID' => $id, 'transStatus' => 'Y']), 'threeDSSessionData' => $encode($id)];
    $mode = 'direct-approved';
    $directOrder = $create();
    $stock = wc_get_product($product->get_id())->get_stock_quantity();
    $beforeCompleted = $completed;
    $direct = $service->start($directOrder, $input());
    $status = $service->status($direct['reference'], $direct['data']['token']);
    $check($direct['state'] === 'challenge' && $direct['data']['transaction_id'] === $id, 'Direct Sale challenge stores correlated UUID');
    $check(!wc_get_order($directOrder->get_id())->is_paid() && $status['redirect'] === null && $status['retry'] === null,
        'Challenge cannot confirm payment or permit another sale');
    $check($completed === $beforeCompleted, 'No payment completion before financial result');
    $beforeProcess = count(array_filter($calls, fn ($c) => $c === 'Process3DS'));
    $forgedCallback = $challengeCallback;
    $forgedCallback['cres'] = $bad;
    try { $service->callback($direct['reference'], $direct['data']['token'], $forgedCallback); } catch (DomainException) {}
    $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === $beforeProcess, 'Forged direct challenge callback never reaches bank');
    $service->callback($direct['reference'], $direct['data']['token'], $challengeCallback);
    $service->callback($direct['reference'], $direct['data']['token'], $challengeCallback);
    $check(wc_get_order($directOrder->get_id())->is_paid(), 'Verified direct challenge final result pays order');
    $check($completed === $beforeCompleted + 1, 'Direct challenge completes order only once');
    $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === $beforeProcess + 1, 'Repeated challenge callback never repeats Process3DS');
    $check(wc_get_product($product->get_id())->get_stock_quantity() === $stock - 1, 'Direct challenge reduces stock exactly once');

    foreach (['direct-decline', 'direct-forged', 'direct-conflict', 'direct-unknown'] as $mode) {
        $declineOrder = $create();
        $attempt = $service->start($declineOrder, $input());
        $beforeCompleted = $completed;
        $beforeProcess = count(array_filter($calls, fn ($c) => $c === 'Process3DS'));
        $service->callback($attempt['reference'], $attempt['data']['token'], $challengeCallback);
        $service->callback($attempt['reference'], $attempt['data']['token'], $challengeCallback);
        $status = $service->status($attempt['reference'], $attempt['data']['token']);
        $verified = $mode === 'direct-decline';
        $check($status['state'] === ($verified ? 'declined' : 'unknown') && ($status['retry'] !== null) === $verified,
            'Only verified decline permits retry: ' . $mode);
        $check(wc_get_order($declineOrder->get_id())->get_status() === ($verified ? 'failed' : 'on-hold'), 'Order follows verified outcome: ' . $mode);
        $check(!wc_get_order($declineOrder->get_id())->is_paid() && $completed === $beforeCompleted, 'Authentication success alone never pays order: ' . $mode);
        $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === $beforeProcess + 1, 'Final callback deduplicated: ' . $mode);
        $beforeSale = count(array_filter($calls, fn ($c) => $c === 'Sale'));
        if ($verified) {
            $next = $service->start(wc_get_order($declineOrder->get_id()), $input());
            $check($next['reference'] !== $attempt['reference'] && $next['state'] === 'challenge', 'Verified 59 allows a fresh explicitly requested attempt');
        } else {
            $service->start(wc_get_order($declineOrder->get_id()), ['y2k_csrf' => Payments::csrf()]);
        }
        $check(count(array_filter($calls, fn ($c) => $c === 'Sale')) === $beforeSale + ($verified ? 1 : 0), 'Unknown outcomes cannot resend Sale: ' . $mode);
    }
    foreach (['direct-camel', 'direct-upper', 'fingerprint-challenge'] as $mode) {
        $variantOrder = $create();
        $stock = wc_get_product($product->get_id())->get_stock_quantity();
        $beforeCompleted = $completed;
        $variant = $service->start($variantOrder, $input());
        if ($mode === 'fingerprint-challenge') {
            $check($variant['state'] === 'fingerprint', 'Fingerprint starts with the original signature');
            $service->callback($variant['reference'], $variant['data']['token'], $callback);
            $variant = (new Store())->find($variant['reference']);
        }
        $check($variant['state'] === 'challenge' && $variant['data']['transaction_id'] === $id && !wc_get_order($variantOrder->get_id())->is_paid(), 'Casing variant waits for correlated financial result: ' . $mode);
        $processCalls = count(array_filter($calls, fn ($c) => $c === 'Process3DS'));
        $service->callback($variant['reference'], $variant['data']['token'], $challengeCallback);
        $service->callback($variant['reference'], $variant['data']['token'], $challengeCallback);
        $check(wc_get_order($variantOrder->get_id())->is_paid() && $completed === $beforeCompleted + 1, 'Casing variant confirms exactly once: ' . $mode);
        $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === $processCalls + 1, 'Casing variant callback remains deduplicated: ' . $mode);
        $check(wc_get_product($product->get_id())->get_stock_quantity() === $stock - 1, 'Casing variant reduces stock once: ' . $mode);
    }
    $mode = 'direct-setup-bad';
    $setupOrder = $create();
    $setup = $service->start($setupOrder, $input());
    $status = $service->status($setup['reference'], $setup['data']['token']);
    $check($setup['state'] === 'declined' && $status['reason'] === 'authentication_setup_failed' && $status['retry'] !== null, 'Known pre-authentication setup failure remains retryable');
    $check($setup['data']['diagnostic']['three_ds_failure'] === 'invalid_creq', 'Specific setup failure persisted encrypted');
    $summary = $server->dispatch(new WP_REST_Request('GET', '/y2k-lafise/v1/attempts/' . $setup['reference']))->get_data();
    $check($summary['diagnostic']['three_ds_failure'] === 'invalid_creq' && !isset($summary['receipt']) && !isset($summary['token']), 'Administrative API exposes only safe 3DS subtype');

    $mode = 'direct-process-bad';
    $uncertainOrder = $create();
    $uncertain = $service->start($uncertainOrder, $input());
    $service->callback($uncertain['reference'], $uncertain['data']['token'], $challengeCallback);
    $store = new Store();
    $uncertain = $store->find($uncertain['reference']);
    $originalDiagnostic = $uncertain['data']['diagnostic'];
    $check($uncertain['state'] === 'unknown' && $originalDiagnostic['three_ds_failure'] === 'invalid_creq', 'Submitted Process3DS failure remains uncertain');
    $check(!str_contains(wp_json_encode($uncertain), 'SYNTHETIC-PRIVATE-BODY'), 'Bank body excluded from persistent diagnostic');
    $beforeCalls = count(array_filter($calls, fn ($c) => in_array($c, ['Sale', 'Process3DS'], true)));
    $service->status($uncertain['reference'], $uncertain['data']['token']);
    $service->reconcile($uncertain['reference']);
    $after = $store->find($uncertain['reference']);
    $check($after['data']['reason'] === 'bank_result_unavailable' && $after['data']['diagnostic'] === $originalDiagnostic, 'Manual-review reconciliation preserves primary failure');
    $data = $after['data']; $data['queried_at'] = 0;
    $data['ref3'] = '000001'; $data['receipt'] = ['bank_reference' => 'BANKREF', 'authorization' => 'TEST'];
    $store->save($after, 'unknown', $data);
    $mode = 'query-timeout';
    $service->reconcile($uncertain['reference']);
    $after = $store->find($uncertain['reference']);
    $check($after['data']['reason'] === 'bank_result_unavailable' && $after['data']['diagnostic'] === $originalDiagnostic, 'Inconclusive bank query preserves specific 3DS diagnostic');
    $check($service->status($after['reference'], $after['data']['token'])['retry'] === null && !wc_get_order($uncertainOrder->get_id())->is_paid(), 'Inconclusive query cannot enable payment retry');
    $data = $after['data']; $data['queried_at'] = 0; $store->save($after, 'unknown', $data);
    $mode = 'query-approved'; $beforeCompleted = $completed;
    $service->reconcile($uncertain['reference']); $service->reconcile($uncertain['reference']);
    $after = $store->find($uncertain['reference']);
    $check($after['state'] === 'approved' && !isset($after['data']['reason']) && !isset($after['data']['diagnostic']), 'Verified query approval clears obsolete active failure');
    $check(wc_get_order($uncertainOrder->get_id())->is_paid() && $completed === $beforeCompleted + 1, 'Query approval completes order once');
    $check(count(array_filter($calls, fn ($c) => in_array($c, ['Sale', 'Process3DS'], true))) === $beforeCalls, 'Recovery never resends Sale or Process3DS');
    foreach (['auth-failure-fingerprint', 'direct-auth-failure', 'fingerprint-challenge-auth-failure', 'direct-auth-conflict'] as $mode) {
        $authOrder = $create();
        $stock = wc_get_product($product->get_id())->get_stock_quantity();
        $beforeCompleted = $completed;
        $authAttempt = $service->start($authOrder, $input());
        $stockAfterStart = wc_get_product($product->get_id())->get_stock_quantity();
        $activeReference = $authAttempt['reference'];
        $return = $authAttempt['state'] === 'fingerprint' ? $callback : $challengeCallback;
        $service->callback($authAttempt['reference'], $authAttempt['data']['token'], $return);
        if ($mode === 'fingerprint-challenge-auth-failure') {
            $nextStage = $store->find($authAttempt['reference']);
            $check($nextStage['state'] === 'challenge' && $nextStage['data']['three_ds_context']['submitted'] === false, 'Continuation opens a fresh unsubmitted challenge');
            $return = $challengeCallback;
            $service->callback($authAttempt['reference'], $authAttempt['data']['token'], $return);
        }
        $activeReference = null;
        $after = $store->find($authAttempt['reference']);
        $status = $service->status($after['reference'], $after['data']['token']);
        $safe = $mode !== 'direct-auth-conflict';
        $check($status['state'] === ($safe ? 'declined' : 'unknown') && ($status['retry'] !== null) === $safe, 'Only proven authentication failure allows retry: ' . $mode);
        if ($safe) {
            $check($after['data']['reason'] === 'authentication_failed' && $after['data']['diagnostic']['response_code'] === '3DS-RES-003', 'Safe authentication failure reason and bank code persisted');
            $check(wc_get_order($authOrder->get_id())->get_status() === 'failed', 'Authentication failure marks WooCommerce order failed');
        }
        $check(!str_contains(wp_json_encode($after), 'SYNTHETIC-PRIVATE-BODY'), 'Authentication failure never stores bank body');
        $check(!wc_get_order($authOrder->get_id())->is_paid() && $completed === $beforeCompleted, 'Authentication failure never confirms payment: ' . $mode);
        $check(wc_get_product($product->get_id())->get_stock_quantity() === $stockAfterStart, 'Authentication failure does not reduce stock again: ' . $mode);
        $beforeCalls = count($calls);
        $service->callback($after['reference'], $after['data']['token'], $return);
        $service->reconcile($after['reference']);
        $check(count($calls) === $beforeCalls, 'Repeated callback and reconciliation never repeat authentication submission');
        $sales = count(array_filter($calls, fn ($c) => $c === 'Sale'));
        if ($safe) {
            $newAttempt = $service->start(wc_get_order($authOrder->get_id()), $input());
            $check($newAttempt['reference'] !== $after['reference'], 'Explicit retry creates a new attempt');
        } else {
            $service->start(wc_get_order($authOrder->get_id()), ['y2k_csrf' => Payments::csrf()]);
        }
        $check(count(array_filter($calls, fn ($c) => $c === 'Sale')) === $sales + ($safe ? 1 : 0), 'Uncertain authentication cannot resend Sale');
    }
    $check(count($submissionProofs) === 5 && !in_array(false, $submissionProofs, true), 'Every Process3DS is durably marked submitted before HTTP');

    foreach (['status', 'callback', 'reconcile', 'start'] as $entry) {
        $mode = 'fingerprint';
        $timeoutOrder = $create();
        $timeout = $service->start($timeoutOrder, $input());
        $check($timeout['data']['auth_expires'] >= time() + 58 && $timeout['data']['auth_expires'] <= time() + 60, 'New fingerprint deadline is 60 seconds');
        $check(wp_next_scheduled('y2k_lafise_reconcile', [$timeout['reference']]) === $timeout['data']['auth_expires'] + 1, 'Fingerprint deadline is not suppressed by the initial recovery event');
        $data = $timeout['data']; $data['auth_expires'] = time() - 1;
        $store->save($timeout, 'fingerprint', $data);
        $beforeProcess = count(array_filter($calls, fn ($c) => $c === 'Process3DS'));
        if ($entry === 'callback') { $service->callback($timeout['reference'], $data['token'], $callback); }
        elseif ($entry === 'reconcile') { $service->reconcile($timeout['reference']); }
        elseif ($entry === 'start') { $service->start(wc_get_order($timeoutOrder->get_id()), $input()); }
        else { $service->status($timeout['reference'], $data['token']); }
        $after = $store->find($timeout['reference']);
        $check($after['state'] === 'declined' && $after['data']['reason'] === 'authentication_expired'
            && $after['data']['diagnostic']['three_ds_failure'] === 'fingerprint_callback_timeout' && !isset($after['data']['action']), 'Fingerprint expires safely through ' . $entry);
        $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === $beforeProcess, 'Expiry never sends Process3DS: ' . $entry);
    }
    $mode = 'direct-approved';
    $challengeOrder = $create(); $waiting = $service->start($challengeOrder, $input());
    $check($waiting['data']['auth_expires'] >= time() + 598 && $waiting['data']['auth_expires'] <= time() + 600, 'Manual challenge keeps ten-minute deadline');
    $data = $waiting['data']; $data['auth_expires'] = time() + 539;
    $store->save($waiting, 'challenge', $data);
    $check($service->status($waiting['reference'], $data['token'])['state'] === 'challenge', 'Challenge remains active after 61 seconds');
    $service->callback($waiting['reference'], $data['token'], $challengeCallback);
    $check(wc_get_order($challengeOrder->get_id())->is_paid(), 'Challenge after 61 seconds can still complete');
    $timeoutChallenge = $service->start($create(), $input());
    $data = $timeoutChallenge['data']; $data['auth_expires'] = time() - 1;
    $store->save($timeoutChallenge, 'challenge', $data);
    $service->status($timeoutChallenge['reference'], $data['token']);
    $check($store->find($timeoutChallenge['reference'])['data']['diagnostic']['three_ds_failure'] === 'challenge_callback_timeout', 'Challenge expiry records its distinct subtype');

    foreach (['authorization', 'provider_reference', 'receipt_authorization', 'receipt_reference', 'submitted', 'paid'] as $evidence) {
        $mode = 'fingerprint'; $guardOrder = $create(); $guard = $service->start($guardOrder, $input());
        $data = $guard['data']; $data['auth_expires'] = time() - 1;
        if ($evidence === 'submitted') { $data['three_ds_context']['submitted'] = true; }
        elseif ($evidence === 'paid') { $guardOrder->payment_complete('SYNTHETIC'); }
        elseif ($evidence === 'receipt_authorization') { $data['receipt']['authorization'] = 'CONFLICT'; }
        elseif ($evidence === 'receipt_reference') { $data['receipt']['bank_reference'] = 'CONFLICT'; }
        else { $data[$evidence] = 'CONFLICT'; }
        $store->save($guard, 'fingerprint', $data);
        $status = $service->status($guard['reference'], $data['token']);
        $check($status['state'] === 'unknown' && $status['retry'] === null && $status['action'] === null, 'Financial or submitted evidence blocks expiry retry: ' . $evidence);
        $beforeProcess = count(array_filter($calls, fn ($c) => $c === 'Process3DS'));
        $service->callback($guard['reference'], $data['token'], $callback);
        $check(count(array_filter($calls, fn ($c) => $c === 'Process3DS')) === $beforeProcess, 'Blocked expiry never submits late callback');
    }
    $mode = 'fingerprint'; $legacy = $service->start($create(), $input());
    $data = $legacy['data']; unset($data['three_ds_context']); $data['auth_expires'] = time() + 500;
    $store->save($legacy, 'fingerprint', $data);
    $check($service->status($legacy['reference'], $data['token'])['state'] === 'fingerprint'
        && $store->find($legacy['reference'])['data']['auth_expires'] === $data['auth_expires'], 'Legacy persisted deadline remains unchanged');
    $mode = 'auth-failure-fingerprint';
    $service->callback($legacy['reference'], $data['token'], $callback);
    $check($service->status($legacy['reference'], $data['token'])['state'] === 'unknown', 'Legacy attempt without stage proof cannot classify bare rejection');

    $mode = 'direct-auth-failure'; $paidOrder = $create(); $alreadyPaid = $service->start($paidOrder, $input());
    $paidOrder->payment_complete('SYNTHETIC-EXTERNAL');
    $beforeCalls = count($calls);
    $service->callback($alreadyPaid['reference'], $alreadyPaid['data']['token'], $challengeCallback);
    $check(count($calls) === $beforeCalls && $service->status($alreadyPaid['reference'], $alreadyPaid['data']['token'])['retry'] === null,
        'Already paid order never submits a pending continuation or becomes retryable');

    echo 'PASS: '.$checks.' integration assertions; HPOS='.(get_option('woocommerce_custom_orders_table_enabled') ?: 'no').'; WordPress='.get_bloginfo('version').'; WooCommerce='.WC_VERSION.'; PHP='.PHP_VERSION."\n";
} finally {
    remove_filter('pre_http_request',$http,10);
    $user->remove_cap('y2k_lafise_test');
    foreach($orders as $orderId) { $o=wc_get_order($orderId); if($o) $o->delete(true); }
    $product->delete(true);
    update_option('woocommerce_y2kwebs_lafise_settings',['enabled'=>'no']);
}
