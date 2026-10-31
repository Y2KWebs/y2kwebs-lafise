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
add_action('woocommerce_payment_complete', static function () use (&$completed): void { $completed++; });
$id = '00000000-0000-4000-8000-000000000001';
$encode = static fn ($v) => rtrim(strtr(base64_encode(is_array($v) ? json_encode($v) : $v), '+/', '-_'), '=');
$http = static function ($pre, $args, $url) use (&$calls, &$mode, $pem, $id, $encode) {
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
        }
    } elseif ($operation === 'Process3DS') {
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
    echo 'PASS: '.$checks.' integration assertions; HPOS='.(get_option('woocommerce_custom_orders_table_enabled') ?: 'no').'; WordPress='.get_bloginfo('version').'; WooCommerce='.WC_VERSION.'; PHP='.PHP_VERSION."\n";
} finally {
    remove_filter('pre_http_request',$http,10);
    $user->remove_cap('y2k_lafise_test');
    foreach($orders as $orderId) { $o=wc_get_order($orderId); if($o) $o->delete(true); }
    $product->delete(true);
    update_option('woocommerce_y2kwebs_lafise_settings',['enabled'=>'no']);
}
