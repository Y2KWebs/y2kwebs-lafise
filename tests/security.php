<?php
/** Local input, markup and HTTP regressions. The web container's fixture intercepts all bank HTTP. */
if (!defined('WP_CLI') || !WP_CLI || home_url() !== 'http://localhost:8087') { exit('Local tests only.'); }
use Y2KWebs\Lafise\{Gateway, Payments, Routes, Store, ThreeDS};

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
};
$postBefore = $_POST;
$order = null;
$attempt = null;
try {
    foreach ([str_repeat('a', 64), 'e30.e30.e30.e30.e30', 'YWJj+/==', 'YWJj_-', '1920'] as $value) {
        $_POST = ['fixture' => wp_slash($value)];
        $assert(Routes::post('fixture') === $value, 'Cryptographic input bytes preserved');
    }
    foreach (["abc\ndef", ' abc ', 'abc%41', '<b>abc</b>', 'abc<script>alert(1)</script>', 'not allowed!', str_repeat('a', 16385)] as $value) {
        $_POST = ['fixture' => wp_slash($value)];
        $rejected = false;
        try { Routes::post('fixture'); } catch (DomainException) { $rejected = true; }
        $assert($rejected, 'Malformed or altered payment data rejected');
    }
    $_POST = ['fixture' => ['array']];
    $assert(Routes::post('fixture') === '', 'Array input cannot become a payment token');
    $assert(Routes::post('absent') === '', 'Missing input remains empty');

    $gateway = new Gateway();
    ob_start(); $gateway->payment_fields(); $html = ob_get_clean();
    $dom = new DOMDocument();
    $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $inputs = $dom->getElementsByTagName('input');
    $assert($inputs->length === 4, 'Escaped classic checkout retains all card inputs');
    foreach ($inputs as $input) {
        $assert(!$input->hasAttribute('name') && $input->hasAttribute('data-card') && $input->hasAttribute('autocomplete')
            && $input->hasAttribute('inputmode') && $input->hasAttribute('maxlength'), 'Escaping preserves card behavior without plaintext serialization');
    }
    $assert(str_contains($html, 'data-y2k-card') && str_contains($html, 'data-y2k-error'), 'Checkout selectors survive escaping');
    $assert(!str_contains(wp_kses('<script>alert(1)</script><input data-card="cvv" onclick="alert(1)" name="raw-card">', Gateway::fieldTags()), '<script')
        && !str_contains(wp_kses('<input onclick="alert(1)" name="raw-card">', Gateway::fieldTags()), 'onclick'), 'Unexpected markup and event handlers stripped');

    $order = wc_create_order();
    $order->set_payment_method('y2kwebs_lafise'); $order->set_currency('USD'); $order->set_total('1.00'); $order->save();
    $store = new Store();
    $id = '00000000-0000-4000-8000-000000000001';
    $encode = static fn ($value) => rtrim(strtr(base64_encode(is_array($value) ? wp_json_encode($value) : $value), '+/', '-_'), '=');
    $attempt = $store->create($order->get_id(), ['token' => bin2hex(random_bytes(32)), 'ticket_hash' => hash('sha256', random_bytes(32)),
        'amount' => 100, 'tax' => 0, 'currency' => 'USD', 'merchant_id' => 'FIXTUREM', 'terminal_id' => 'FIXTURET',
        'expires' => time() + 600, 'auth_expires' => time() + 600, 'steps' => [], 'transaction_id' => $id,
        'receipt' => ['bank_reference' => '', 'authorization' => ''],
        'action' => ThreeDS::action('challenge', 'https://issuer.bank.com/acs',
            ['creq' => $encode(['threeDSServerTransID' => $id]), 'threeDSSessionData' => $encode($id)])]);
    $attempt = $store->save($attempt, 'challenge', $attempt['data']);
    $request = static function (string $route, array $args = []) use ($attempt) {
        $path = wp_parse_url(Payments::url($route, $attempt), PHP_URL_PATH);
        return wp_remote_request('http://wordpress' . $path, $args + ['timeout' => 15, 'redirection' => 0, 'headers' => ['Host' => 'localhost:8087']]);
    };
    $response = $request('verify');
    $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200, 'Standalone verify route responds');
    $body = wp_remote_retrieve_body($response);
    $dom->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING);
    $frames = $dom->getElementsByTagName('iframe');
    $assert($frames->length === 1 && $frames->item(0)->getAttribute('name') === 'y2k-bank'
        && $frames->item(0)->hasAttribute('hidden') && str_contains($frames->item(0)->getAttribute('sandbox'), 'allow-forms'), 'KSES preserves the isolated challenge iframe');
    $assert(str_contains($body, 'data-status-url=') && str_contains($body, 'data-message'), 'Verification selectors and status URL preserved');
    $assert(str_contains($body, 'checkout.css?ver=0.2.1') && str_contains($body, 'verify.js?ver=0.2.1'), 'WordPress prints versioned stylesheet and verification script');
    $assert(str_contains(wp_remote_retrieve_header($response, 'content-security-policy'), "script-src 'self'")
        && wp_remote_retrieve_header($response, 'referrer-policy') === 'no-referrer', 'Standalone security headers retained');
    $bad = ['cres' => $encode(['threeDSServerTransID' => '00000000-0000-4000-8000-000000000002']), 'threeDSSessionData' => $encode($id)];
    $response = $request('callback', ['method' => 'POST', 'body' => $bad]);
    $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 400 && $store->find($attempt['reference'])['state'] === 'challenge',
        'Forged HTTP callback rejected without changing the attempt');
    $callback = ['cres' => $encode(['threeDSServerTransID' => $id, 'transStatus' => 'Y']), 'threeDSSessionData' => $encode($id)];
    $response = $request('callback', ['method' => 'POST', 'body' => $callback]);
    $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
        && str_contains(wp_remote_retrieve_body($response), 'callback.js?ver=0.2.1'), 'Correlated bank callback works without a WordPress browser nonce');
    $stored = $store->find($attempt['reference']);
    $assert($stored['state'] === 'unknown' && count($stored['data']['steps']) === 1
        && ($stored['data']['diagnostic']['failure'] ?? '') === 'transport_failed', 'Local web fixture intercepts Process3DS and retains uncertainty');
    $response = $request('callback', ['method' => 'POST', 'body' => $callback]);
    $assert(wp_remote_retrieve_response_code($response) === 200 && count($store->find($attempt['reference'])['data']['steps']) === 1,
        'Repeated HTTP callback cannot process another financial request');
    echo "PASS: $checks input, markup and HTTP security assertions; bank transport simulated.\n";
} finally {
    $_POST = $postBefore;
    if ($order) { $order->delete(true); }
    if ($attempt) {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'y2k_lafise_attempts', ['id' => $attempt['id']], ['%d']);
    }
}
