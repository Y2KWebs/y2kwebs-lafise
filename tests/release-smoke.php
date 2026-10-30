<?php
if (!defined('WP_CLI') || !WP_CLI || home_url() !== 'http://localhost:8087') { exit('Local tests only.'); }
add_filter('pre_http_request', static fn () => new WP_Error('release_test_blocked', 'No external requests in release smoke test.'), PHP_INT_MAX);
wp_set_current_user(1);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
};
$root = dirname(Y2K_LAFISE_FILE);
$assert(!is_dir($root . '/tests') && !is_dir($root . '/scripts'), 'ZIP mount must exclude development files');
$assert(Y2K_LAFISE_VERSION === '0.2.0', 'Installed ZIP version');
$assert(is_plugin_active('y2kwebs-lafise/y2kwebs-lafise.php'), 'Installed ZIP activated');
$assert(current_user_can('manage_woocommerce'), 'Administrator has settings access');
$gateway = new Y2KWebs\Lafise\Gateway();
ob_start();
$gateway->admin_options();
$html = ob_get_clean();
$assert(str_contains($html, 'woocommerce_y2kwebs_lafise_password'), 'Native password settings render');
$assert(str_contains($html, 'woocommerce_y2kwebs_lafise_merchant_pay_id'), 'Native merchant settings render');
$assert(str_contains($html, 'y2k_lafise_diagnose'), 'Connection check available');
$server = rest_get_server();
$response = $server->dispatch(new WP_REST_Request('GET', '/y2k-lafise/v1/settings'));
$assert($response->get_status() === 200, 'Administrator can access settings API');
$data = $response->get_data();
$assert(!array_key_exists('password', $data) && !array_key_exists('merchant_pay_id', $data), 'Settings API redacts credentials');
global $wpdb;
$table = $wpdb->prefix . 'y2k_lafise_attempts';
$assert($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table, 'Activation installs attempts table');
echo "PASS: $checks release ZIP assertions; settings HTML rendered and admin API accessible; no bank requests.\n";
