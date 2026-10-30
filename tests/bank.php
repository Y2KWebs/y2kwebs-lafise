<?php
/** Standalone transport tests. All payloads, access values and responses are synthetic. */
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    $prefix = 'Y2KWebs\\Lafise\\';
    if (str_starts_with($class, $prefix)) { require_once dirname(__DIR__) . '/src/' . substr($class, strlen($prefix)) . '.php'; }
});
define('Y2K_LAFISE_PRIVATE', ['username' => 'fixture', 'password' => 'fixture-not-a-secret',
    'merchant_pay_id' => 'fixture-key', 'merchant_id' => 'TESTM', 'terminal_id' => 'TESTT']);
function get_option($key, $default = false) { return $default; }
function wp_json_encode($value) { return json_encode($value); }
final class WP_Error { public function __construct(public string $code, public string $message) {} }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($value): int { return $value['response']['code']; }
function wp_remote_retrieve_body($value): string { return $value['body']; }
$calls = [];
$response = [];
function wp_safe_remote_post($url, $args) {
    global $calls, $response;
    $calls[] = basename($url);
    if ($response instanceof Throwable) { throw $response; }
    return $response;
}
use Y2KWebs\Lafise\{Bank, Config};
$checks = 0;
$assert = static function (bool $ok, string $name) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($name); }
    $checks++;
};
$context = ['reference' => 'FIXTURE01', 'amount' => '100', 'tax' => '0', 'currency' => 'USD',
    'description' => 'Synthetic test', 'request_date' => '29102026120000', 'first_name' => 'Fixture',
    'last_name' => 'Test', 'phone' => '', 'email' => 'fixture@example.invalid', 'country' => 'CR',
    'payer_ip' => '127.0.0.1', 'response_url' => 'https://example.invalid/return',
    'encrypted_card' => 'e30.e30.e30.e30.e30', 'type_request' => 'Production',
    'payment_type' => 4, 'screen_height' => '800', 'screen_width' => '1200'];
$auth = ['token' => 'synthetic-token'];
$privateMarker = 'SYNTHETIC_PRIVATE_BODY_MUST_NOT_BE_RETAINED';
$bank = new Bank(new Config());
$respond = static fn (int $status, mixed $body): array => ['response' => ['code' => $status],
    'body' => is_string($body) ? $body : json_encode($body)];

$response = new WP_Error('http_request_failed', $privateMarker);
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['state'] === 'unknown' && $result['diagnostic']['failure'] === 'transport_failed', 'Transport uncertainty retained');
$assert(!str_contains(json_encode($result), $privateMarker), 'Transport message excluded');

$response = $respond(503, $privateMarker);
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['state'] === 'unknown' && $result['diagnostic']['http_status'] === 503, 'HTTP status retained');
$assert(!str_contains(json_encode($result), $privateMarker), 'HTTP body excluded');

$response = $respond(200, $privateMarker);
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['diagnostic']['stage'] === 'decode' && $result['diagnostic']['failure'] === 'response_format_invalid', 'Malformed JSON distinguished');
$assert(!str_contains(json_encode($result), $privateMarker), 'Malformed content excluded');

$response = $respond(200, ['ResponseCode' => '06', 'Description' => $privateMarker]);
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['state'] === 'unknown' && $result['diagnostic']['response_code'] === '06', 'Response code does not imply a verified decline');
$assert($result['diagnostic']['failure'] === 'response_fields_missing', 'Missing financial fields identified');
$assert(!str_contains(json_encode($result), $privateMarker), 'Arbitrary bank fields excluded');

$payload = ['ResponseCode' => '00', 'PaymentData' => ['Amount' => '100', 'Currency_code' => 'USD',
    'Merchant_Id' => 'TESTM', 'Terminal_Id' => 'TESTT', 'Ref_1' => 'SYNTHETICREF', 'Ref_3' => '000001'],
    'AuthorizationCode' => 'TEST', 'ApiSecurity' => ['Message_signature' => str_repeat('0', 64)]];
$response = $respond(200, $payload);
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['state'] === 'unknown' && $result['diagnostic']['failure'] === 'signature_mismatch', 'Invalid signature never approved');
$payload['ApiSecurity']['Message_signature'] = hash('sha256', 'fixture-key' . 'SYNTHETICREF' . '100' . 'TESTM' . '0' . 'USD');
$response = $respond(200, json_encode(json_encode($payload)));
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['state'] === 'unknown' && $result['reason'] === 'unrecognized_bank_result', 'Unverified direct approval remains unknown');
$assert($result['diagnostic']['response_code'] === '00', 'Double-encoded Sale response decoded');

$response = $respond(200, ['ResponseCode' => $privateMarker]);
$result = $bank->execute('sale', $context, [], $auth);
$assert(!isset($result['diagnostic']['response_code']) && !str_contains(json_encode($result), $privateMarker), 'Nonstandard response code not retained');

$response = new RuntimeException($privateMarker);
$result = $bank->execute('sale', $context, [], $auth);
$assert($result['state'] === 'unknown' && !str_contains(json_encode($result), $privateMarker), 'Unexpected exception message excluded');

$calls = [];
$incomplete = $context;
unset($incomplete['first_name']);
$result = $bank->execute('sale', $incomplete, [], $auth);
$assert($result['state'] === 'declined' && $result['reason'] === 'payment_not_sent', 'Preparation failure is known not sent');
$assert($calls === [] && $result['diagnostic']['stage'] === 'prepare', 'No financial HTTP call on preparation failure');

$response = new WP_Error('http_request_failed', $privateMarker);
$result = $bank->execute('sale', $context);
$assert($result['reason'] === 'payment_not_sent' && $calls === ['tokenex'], 'Failed authentication does not submit Sale');
$assert($result['diagnostic']['operation'] === 'authenticate', 'Authentication failure identified');
echo "OK: $checks bank diagnostic assertions\n";
