<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $name = substr($class, strlen('Y2KWebs\\Lafise\\'));
    require_once dirname(__DIR__) . '/src/' . $name . '.php';
});
use Y2KWebs\Lafise\{Money, Protocol, ThreeDS, Bank};

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
$html = '<form method="POST" action="https://issuer.bank.com/acs"><input name="creq" value="abc"><input name="threeDSSessionData" value="def"></form>';
check(Bank::continuation($html)['Creq'] === 'abc', 'Parse bank HTML without execution');
rejects(fn () => Bank::continuation($html.$html), 'Ambiguous forms');
echo "OK: $count unit assertions\n";
