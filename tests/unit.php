<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $name = substr($class, strlen('Y2KWebs\\Lafise\\'));
    require_once dirname(__DIR__) . '/src/' . $name . '.php';
});
use Y2KWebs\Lafise\{Money, Protocol, ThreeDS, Bank};

// Minimal WordPress compatibility shim for the standalone protocol suite.
function wp_parse_url(string $url): array|false { return parse_url($url); }

$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) { throw new RuntimeException($message); }
    $count++;
}
function rejects(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable) { check(true, $label); return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
$encode = static fn ($v) => rtrim(strtr(base64_encode(is_array($v) ? json_encode($v) : $v), '+/', '-_'), '=');
$id = '00000000-0000-4000-8000-000000000001';
check(Money::minor('1.01') === 101, 'Decimal conversion');
check(Money::minor('0.1') === 10, 'Tenths');
check(Money::major(10001) === '100.01', 'Formatting');
foreach (['-1', '1e3', '1.001', '1,00', ' 1', '9999999999999', '01'] as $value) {
    rejects(fn () => Money::minor($value), 'Malformed amount');
}
foreach (['http://bank.com/a', 'https://127.0.0.1/a', 'https://[::1]/', 'https://user:pass@bank.com',
    'https://issuer.local', 'https://issuer.test', 'https://bank.com:8443', "https://bank.com/\n", 'javascript:alert(1)',
    'https://bank.com\\@evil.com', 'https://bank.com/#x'] as $url) {
    rejects(fn () => ThreeDS::destination($url), 'Unsafe issuer');
}
check(ThreeDS::destination('https://issuer.newbank.com/acs') === 'https://issuer.newbank.com/acs', 'New issuer allowed from bank');
$action = ThreeDS::action('challenge', 'https://issuer.bank.com/acs', ['creq' => $encode(['threeDSServerTransID' => $id]), 'threeDSSessionData' => $encode($id)]);
check($action['transaction_id'] === $id, 'Correlated challenge');
rejects(fn () => ThreeDS::action('challenge', 'https://issuer.bank.com/acs', $action['fields'], 'different'), 'Cross transaction');
check(ThreeDS::callback(['cres' => $encode(['threeDSServerTransID' => $id]), 'threeDSSessionData' => $encode($id)], 'challenge', $id)['cres'] !== '', 'Challenge return');
rejects(fn () => ThreeDS::callback(['cres' => $encode(['threeDSServerTransID' => $id]), 'threeDSSessionData' => $encode('different')], 'challenge', $id), 'Wrong callback');

$protocol = new Protocol();
$ctx = ['merchant_pay_id' => '00000000-0000-4000-8000-000000000001', 'reference' => 'TEST01',
    'merchant_id' => '1234567890', 'terminal_id' => 'TESTTERM', 'amount' => '100', 'tax' => '0', 'currency' => 'USD'];
check(Protocol::sign(['merchant_pay_id','reference','merchant_id','amount','tax','currency'], $ctx)
    === 'f7756f9d6b19ddbd820b6d3735bc7907ad893cbb5a8950aed192f94431ddafaa', 'Independent request signature vector');
$payload = ['ResponseCode' => '00', 'PaymentInformation' => ['Amount' => '1.00', 'Currency_code' => 'USD',
    'Merchant_Id' => '1234567890', 'Terminal_Id' => 'TESTTERM', 'AuthorizationCode' => 'TESTAUTH', 'Ref1' => 'BANK001', 'Ref3' => '000123 ']];
$payload['ApiSecurity']['Message_signature'] = hash('sha256', $ctx['merchant_pay_id'] . 'BANK001' . '00' . '1.00' . '1234567890' . '0' . 'USD');
$approved = $protocol->validate('process', $payload, $ctx);
check($approved['state'] === 'approved' && $approved['ref3'] === '000123', 'Approve signed matching payment, keep leading zeros');
$tampered = $payload; $tampered['PaymentInformation']['Amount'] = '2.00';
rejects(fn () => $protocol->validate('process', $tampered, $ctx), 'Tampered amount');
$tampered = $payload; $tampered['PaymentInformation']['Currency_code'] = 'CRC';
rejects(fn () => $protocol->validate('process', $tampered, $ctx), 'Wrong currency');
$tampered = $payload; $tampered['PaymentInformation']['Terminal_Id'] = 'OTHER';
rejects(fn () => $protocol->validate('process', $tampered, $ctx), 'Wrong terminal');
$tampered = $payload; $tampered['ApiSecurity']['Message_signature'] = str_repeat('0',64);
rejects(fn () => $protocol->validate('process', $tampered, $ctx), 'Forged signature');
$query = ['Status' => 'OK', 'ResponseCode' => '00', 'ExtraFile' => ['OrderId' => '000123', 'Terminal_Id' => 'TESTTERM', 'Currency' => 'USD', 'Amount' => '1.00']];
check($protocol->validate('inquire',$query,$ctx,$approved,true)['state'] === 'approved', 'Anchored status query');
rejects(fn () => $protocol->validate('inquire',$query,$ctx,[],true), 'Query without receipt');
rejects(fn () => $protocol->validate('inquire',$query,$ctx,$approved), 'Query without authenticated transport');
$sale = ['ResponseCode' => '00', 'PaymentData' => ['Amount' => '100', 'Currency_code' => 'USD', 'Merchant_Id' => '1234567890',
    'Terminal_Id' => 'TESTTERM', 'Ref_1' => 'BANK001', 'Ref_3' => '000123'], 'AuthorizationCode' => 'TESTAUTH'];
$sale['ApiSecurity']['Message_signature'] = hash('sha256', $ctx['merchant_pay_id'] . 'BANK001' . '100' . '1234567890' . '0' . 'USD');
check($protocol->validate('sale',$sale,$ctx)['state'] === 'unknown', 'Unverified direct sale is not auto-approved');
$sale['ResponseCode'] = '3DS-PRC-001';
$sale['AuthorizationCode'] = '';
$sale['ExtraFile'] = ['threeDSMethodURL' => 'https://newissuer.bank.com/method', 'threeDSMethodData' => $encode(['threeDSServerTransID'=>$id])];
rejects(fn () => $protocol->validate('sale',$sale,$ctx), '3DS requires authenticated bank transport');
check($protocol->validate('sale',$sale,$ctx,[],true)['state'] === 'fingerprint','Authenticated new issuer');
$sale['ExtraFile']['threeDSMethodURL']='https://issuer.home/method';
check($protocol->validate('sale',$sale,$ctx,[],true)['reason'] === 'authentication_setup_failed','Safe failure before authentication submission');
$challenge = $sale;
$challenge['ResponseCode'] = '3DS-PRC-002';
$challenge['PaymentData']['Ref_1'] = null;
$challenge['PaymentData']['Ref_3'] = '';
$challenge['ExtraFile'] = ['Action' => 'https://issuer.bank.com/acs',
    'Creq' => $encode(['threeDSServerTransID' => $id]), 'ThreeDSSessionData' => $encode($id)];
// Independent vector calculated outside the PHP signer, using fictitious values.
$challenge['ApiSecurity']['Message_signature'] = '581da8be8c3a52176a0678005cfd9c1fb4371bcc938c594c15f6d6731067d51d';
$result = $protocol->validate('sale', $challenge, $ctx, [], true);
check($result['state'] === 'challenge' && $result['receipt']['bank_reference'] === '' && $result['transaction_id'] === $id,
    'Direct challenge preserves minor amount and normalizes null bank reference');
rejects(fn () => $protocol->validate('sale', $challenge, $ctx), 'Direct challenge requires authenticated transport');
$badSignature = $challenge;
$badSignature['ApiSecurity']['Message_signature'] = hash('sha256', $ctx['merchant_pay_id'] . '100' . '1234567890' . '0' . 'USD');
rejects(fn () => $protocol->validate('sale', $badSignature, $ctx, [], true), 'Challenge cannot fall back to fingerprint signature');
$badSignature['ApiSecurity']['Message_signature'] = str_repeat('0', 64);
rejects(fn () => $protocol->validate('sale', $badSignature, $ctx, [], true), 'Forged challenge signature');
$fingerprint = $sale;
$fingerprint['PaymentData']['Ref_1'] = null;
$fingerprint['ExtraFile']['threeDSMethodURL'] = 'https://issuer.bank.com/method';
$fingerprint['ApiSecurity']['Message_signature'] = hash('sha256', $ctx['merchant_pay_id'] . '100' . '1234567890' . '0' . 'USD');
check($protocol->validate('sale', $fingerprint, $ctx, [], true)['state'] === 'fingerprint', 'Fingerprint formula remains unchanged');
$fingerprint['ApiSecurity']['Message_signature'] = $challenge['ApiSecurity']['Message_signature'];
rejects(fn () => $protocol->validate('sale', $fingerprint, $ctx, [], true), 'Challenge signature cannot authenticate fingerprint');
foreach (['59', '96', '06'] as $code) {
    $final = $payload;
    $final['ResponseCode'] = $code;
    $final['PaymentInformation']['AuthorizationCode'] = '';
    $final['ApiSecurity']['Message_signature'] = hash('sha256', $ctx['merchant_pay_id'] . 'BANK001' . $code . '1.00' . '1234567890' . '0' . 'USD');
    check($protocol->validate('process', $final, $ctx)['state'] === ($code === '59' ? 'declined' : 'unknown'), 'Only reviewed Process3DS decline is classified');
    if ($code === '59') {
        $conflict = $final;
        $conflict['PaymentInformation']['AuthorizationCode'] = 'TESTAUTH';
        rejects(fn () => $protocol->validate('process', $conflict, $ctx), 'Contradictory decline authorization');
        $final['ApiSecurity']['Message_signature'] = str_repeat('0', 64);
        rejects(fn () => $protocol->validate('process', $final, $ctx), 'Decline requires verified signature');
    }
}
$directDecline = $sale;
$directDecline['ResponseCode'] = '59';
check($protocol->validate('sale', $directDecline, $ctx)['state'] === 'unknown', 'Sale decline remains unclassified');
foreach ([['Action', 'Creq', 'ThreeDSSessionData'], ['action', 'creq', 'threeDSSessionData'],
    ['action', 'creq', 'threedssessiondata'], ['ACTION', 'CREQ', 'THREEDSSESSIONDATA']] as $names) {
    $variant = $challenge;
    $variant['ExtraFile'] = array_combine($names, array_values($challenge['ExtraFile']));
    $result = $protocol->validate('sale', $variant, $ctx, [], true);
    check($result['state'] === 'challenge' && $result['action']['fields'] === $action['fields']
        && $result['action']['action'] === $challenge['ExtraFile']['Action'], 'Sale casing variants preserve exact values');
    $continuation = ['ResponseCode' => '3DS-PRC-002'] + $variant['ExtraFile'];
    check($protocol->validate('process', $continuation, $ctx, ['transaction_id' => $id, 'receipt' => ['bank_reference' => 'TEST']], true)['state'] === 'challenge',
        'Process3DS JSON accepts only 3DS leaf casing variants');
    rejects(fn () => $protocol->validate('process', $continuation, $ctx, ['transaction_id' => $id, 'receipt' => ['bank_reference' => 'TEST']]), 'Process continuation requires authenticated transport');
}
$fp = $challenge;
$fp['ResponseCode'] = '3DS-PRC-001';
$fp['ApiSecurity']['Message_signature'] = hash('sha256', $ctx['merchant_pay_id'] . '100' . '1234567890' . '0' . 'USD');
$fp['ExtraFile'] = ['threeDSMethodURL' => 'https://issuer.bank.com/method', 'threeDSMethodData' => $encode(['threeDSServerTransID' => $id])];
foreach ([['threeDSMethodURL', 'threeDSMethodData'], ['threedsmethodurl', 'threedsmethoddata'], ['THREEDSMETHODURL', 'THREEDSMETHODDATA']] as $names) {
    $variant = $fp;
    $variant['ExtraFile'] = array_combine($names, array_values($fp['ExtraFile']));
    $result = $protocol->validate('sale', $variant, $ctx, [], true);
    check($result['state'] === 'fingerprint' && $result['action']['fields']['threeDSMethodData'] === $fp['ExtraFile']['threeDSMethodData'], 'Fingerprint preserves base64 bytes across field names');
}
$invalidFields = [
    ['Action', 'http://127.0.0.1/acs', 'invalid_destination'], ['Action', ['not a URL'], 'invalid_destination'],
    ['Creq', 'NOT VALID', 'invalid_creq'], ['Creq', null, 'invalid_creq'], ['Creq', str_repeat('a', 16385), 'invalid_creq'],
    ['ThreeDSSessionData', 'NOT VALID', 'invalid_session_data'], ['ThreeDSSessionData', null, 'invalid_session_data'],
    ['ThreeDSSessionData', $encode('not-a-uuid'), 'invalid_transaction_id'],
    ['Creq', $encode(['threeDSServerTransID' => '00000000-0000-4000-8000-000000000002']), 'challenge_identifier_mismatch'],
    ['ThreeDSSessionData', $encode('00000000-0000-4000-8000-000000000002'), 'challenge_identifier_mismatch'],
];
foreach ($invalidFields as [$field, $value, $reason]) {
    $bad = $challenge;
    $bad['ExtraFile'][$field] = $value;
    $bad['PAN'] = 'SYNTHETIC-MUST-NOT-RETAIN';
    $bad['ExtraFile']['Unrelated'] = 'SYNTHETIC-MUST-NOT-RETAIN';
    $result = $protocol->validate('sale', $bad, $ctx, [], true);
    check($result === ['state' => 'declined', 'reason' => 'authentication_setup_failed', 'diagnostic' => ['three_ds_failure' => $reason]], 'Specific safe setup reason: ' . $reason);
    check(!str_contains(json_encode($result), 'SYNTHETIC-MUST-NOT-RETAIN') && !isset($result['action']), 'Malformed continuation body not retained');
    $bad['AuthorizationCode'] = 'TESTAUTH';
    rejects(fn () => $protocol->validate('sale', $bad, $ctx, [], true), 'Authorization prevents retry on invalid continuation');
}
foreach (['Action' => 'action', 'Creq' => 'creq', 'ThreeDSSessionData' => 'threeDSSessionData'] as $name => $alias) {
    foreach ([true, false] as $sameValue) {
        $bad = $challenge;
        $bad['ExtraFile'][$alias] = $sameValue ? $bad['ExtraFile'][$name] : 'OTHER';
        check($protocol->validate('sale', $bad, $ctx, [], true)['diagnostic']['three_ds_failure'] === 'ambiguous_fields', 'Duplicate 3DS names rejected even with equal values');
        $badProcess = ['ResponseCode' => '3DS-PRC-002'] + $bad['ExtraFile'];
        rejects(fn () => $protocol->validate('process', $badProcess, $ctx, ['transaction_id' => $id, 'receipt' => ['bank_reference' => 'TEST']], true), 'Ambiguous Process3DS JSON rejected');
    }
}
foreach (['threeDSMethodURL', 'threeDSMethodData'] as $name) {
    $bad = $fp; $bad['ExtraFile'][strtoupper($name)] = $bad['ExtraFile'][$name];
    check($protocol->validate('sale', $bad, $ctx, [], true)['diagnostic']['three_ds_failure'] === 'ambiguous_fields', 'Ambiguous fingerprint field rejected');
}
$bad = $challenge;
$bad['ExtraFile'] = ['action' => $challenge['ExtraFile']['Action'], 'creq' => $challenge['ExtraFile']['Creq'], 'threeDSSessionData' => $challenge['ExtraFile']['ThreeDSSessionData']];
$bad['ApiSecurity']['Message_signature'] = str_repeat('0', 64);
rejects(fn () => $protocol->validate('sale', $bad, $ctx, [], true), 'Casing never bypasses financial signature');
$bad = $challenge; $bad['ExtraFile']['action'] = $bad['ExtraFile']['Action']; $bad['PaymentData']['Amount'] = '101';
rejects(fn () => $protocol->validate('sale', $bad, $ctx, [], true), 'Financial validation precedes 3DS ambiguity handling');
$bad = $challenge; $bad['extraFile'] = $bad['ExtraFile']; unset($bad['ExtraFile']);
check($protocol->validate('sale', $bad, $ctx, [], true)['state'] === 'declined', 'Parent container casing is not normalized');
$bad = $challenge; $bad['PaymentData']['amount'] = $bad['PaymentData']['Amount']; unset($bad['PaymentData']['Amount']);
rejects(fn () => $protocol->validate('sale', $bad, $ctx, [], true), 'Financial field casing remains exact');
rejects(fn () => ThreeDS::callback(['CRES' => $encode(['threeDSServerTransID' => $id]), 'THREEDSSESSIONDATA' => $encode($id)], 'challenge', $id), 'Callback POST field names remain exact');
check(ThreeDS::failure(new RuntimeException('Unsafe issuer destination.')) === null
    && ThreeDS::failure(new DomainException('SYNTHETIC-MUST-NOT-RETAIN')) === null, 'Failure mapping never copies arbitrary exception text');
$bad = $fp; $bad['ExtraFile']['threeDSMethodData'] = 'NOT VALID';
check($protocol->validate('sale', $bad, $ctx, [], true)['diagnostic']['three_ds_failure'] === 'invalid_method_data', 'Invalid fingerprint data distinguished');

$html = '<form method="POST" action="https://issuer.bank.com/acs"><input name="creq" value="abc"><input name="threeDSSessionData" value="def"></form>';
check(Bank::continuation($html)['Creq'] === 'abc', 'Parse bank HTML without execution');
rejects(fn () => Bank::continuation($html.$html), 'Ambiguous forms');
$failureContext = $ctx + ['attempt_state' => 'processing', 'order_unpaid' => true];
$failurePayload = ['ResponseCode' => '3DS-RES-003', 'ResponseMessage' => 'SYNTHETIC-PRIVATE', 'PAN' => 'SYNTHETIC-PRIVATE', 'CVV' => 'SYNTHETIC-PRIVATE'];
$trusted = $protocol->validate('sale', $challenge, $ctx, [], true);
$trusted['three_ds_context']['submitted'] = true;
$failure = $protocol->validate('process', $failurePayload, $failureContext, $trusted, true);
check($failure['state'] === 'declined' && $failure['reason'] === 'authentication_failed'
    && !str_contains(json_encode($failure), 'SYNTHETIC-PRIVATE') && !isset($failure['receipt']), 'Bound authentication rejection excludes the bank body');
rejects(fn () => $protocol->validate('process', $failurePayload, $failureContext, $trusted), 'Receipt-free rejection requires authenticated transport');
rejects(fn () => $protocol->validate('sale', $failurePayload, $failureContext, $trusted, true), 'Receipt-free rejection is not a Sale decline');
rejects(fn () => $protocol->validate('process', $failurePayload, $failureContext, [], true), 'Old or missing context cannot classify a rejection');
foreach ([['stage', 'invalid'], ['transaction_id', 'different'], ['reference', 'OTHER'], ['code', '00'],
    ['source_operation', 'inquire'], ['receipt_hash', 'different'], ['submitted', false]] as [$key, $value]) {
    $badPrior = $trusted; $badPrior['three_ds_context'][$key] = $value;
    rejects(fn () => $protocol->validate('process', $failurePayload, $failureContext, $badPrior, true), 'Rejection context binding: ' . $key);
}
foreach (['sending', 'approved', 'declined', 'challenge'] as $state) {
    $badContext = $failureContext; $badContext['attempt_state'] = $state;
    rejects(fn () => $protocol->validate('process', $failurePayload, $badContext, $trusted, true), 'Wrong submitting state: ' . $state);
}
$badContext = $failureContext; $badContext['order_unpaid'] = false;
rejects(fn () => $protocol->validate('process', $failurePayload, $badContext, $trusted, true), 'Paid order cannot become retryable');
foreach (['amount' => '101', 'merchant' => 'OTHER', 'terminal' => 'OTHER', 'currency' => 'CRC', 'code' => '00', 'authorization' => 'AUTH', 'bank_reference' => 'BANK'] as $key => $value) {
    $badPrior = $trusted; $badPrior['receipt'][$key] = $value;
    // Recompute the binding too, to test the underlying financial validation independently.
    $badPrior['three_ds_context']['receipt_hash'] = hash('sha256', json_encode($badPrior['receipt'], JSON_THROW_ON_ERROR));
    rejects(fn () => $protocol->validate('process', $failurePayload, $failureContext, $badPrior, true), 'Prior financial mismatch: ' . $key);
}
foreach (['authorization', 'authorization_code', 'provider_reference', 'bank_reference'] as $key) {
    $badPrior = $trusted; $badPrior[$key] = 'CONTRADICTION';
    rejects(fn () => $protocol->validate('process', $failurePayload, $failureContext, $badPrior, true), 'Saved financial evidence: ' . $key);
}
foreach ([['AuthorizationCode' => 'AUTH'], ['AUTHORIZATION' => 'AUTH'], ['ExtraFile' => ['AuthorizationCode' => 'AUTH']],
    ['PaymentInformation' => ['Amount' => '1.00']], ['paymentdata' => ['amount' => '1.00']], ['PaymentInformation' => 'invalid'],
    ['ApiSecurity' => ['Message_signature' => 'signature']], ['ExtraFile' => ['SIGNATURE' => 'signature']],
    ['Code' => '00'], ['responseCode' => '00'], ['PaymentData' => ['Amount' => 0]], ['PaymentData' => ['Amount' => false]],
    ['ExtraFile' => ['AuthorizationCode' => []]], ['PaymentInformation' => []]] as $extra) {
    rejects(fn () => $protocol->validate('process', $failurePayload + $extra, $failureContext, $trusted, true), 'Financial evidence never uses receipt-free path');
}
check($protocol->validate('process', $failurePayload + ['PaymentInformation' => ['AuthorizationCode' => null, 'Amount' => '']], $failureContext, $trusted, true)['state'] === 'declined', 'Only null or empty financial values are allowed');
rejects(fn () => $protocol->validate('process', ['ResponseCode' => '3DS-INV-003'], $failureContext, $trusted, true), 'Unknown authentication code remains uncertain');
$fingerprintPrior = $protocol->validate('sale', $fp, $ctx, [], true);
$fingerprintPrior['three_ds_context']['submitted'] = true;
check($protocol->validate('process', $failurePayload, $failureContext, $fingerprintPrior, true)['reason'] === 'authentication_failed', 'Fingerprint rejection is correlated');
$nextChallenge = $protocol->validate('process', ['ResponseCode' => '3DS-PRC-002', 'Action' => 'https://issuer.bank.com/acs',
    'Creq' => $encode(['threeDSServerTransID' => $id]), 'ThreeDSSessionData' => $encode($id)], $ctx, $fingerprintPrior, true);
check($nextChallenge['three_ds_context']['submitted'] === false && $nextChallenge['three_ds_context']['code'] === '3DS-PRC-002', 'New challenge resets the per-stage submission marker');
$nextChallenge['three_ds_context']['submitted'] = true;
check($protocol->validate('process', $failurePayload, $failureContext, $nextChallenge, true)['reason'] === 'authentication_failed', 'Fingerprint to challenge rejection retains original receipt binding');

echo "OK: $count unit assertions\n";
