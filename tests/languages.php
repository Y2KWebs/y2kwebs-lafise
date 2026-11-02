<?php
/** Local language regressions. HTTP is restricted to the disposable web container. */
if (!defined('WP_CLI') || !WP_CLI || home_url() !== 'http://localhost:8087') { exit('Local tests only.'); }
use Y2KWebs\Lafise\{Blocks, Gateway, Languages, Plugin};

$checks = 0;
$assert = static function (bool $ok, string $name) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($name); }
    $checks++;
};
add_filter('pre_http_request', static function ($pre, $args, $url) {
    return str_starts_with($url, 'http://wordpress/') ? $pre : new WP_Error('language_test_blocked', 'No external requests.');
}, PHP_INT_MAX, 3);
$oldSettings = get_option('woocommerce_y2kwebs_lafise_settings', []);
$oldLanguage = get_option('WPLANG', '');
$oldUser = get_current_user_id();
$oldUserLocale = get_user_meta(1, 'locale', true);
$regionalPath = WP_LANG_DIR . '/plugins/y2kwebs-lafise-es_AR.mo';
$oldRegional = is_file($regionalPath) ? file_get_contents($regionalPath) : null;
$oldScreen = $GLOBALS['current_screen'] ?? null;
$storeLocaleFilter = null;
try {
    require_once ABSPATH . WPINC . '/pomo/mo.php';
    $regional = new MO();
    $regional->set_header('Content-Type', 'text/plain; charset=UTF-8');
    $regional->set_header('Language', 'es_AR');
    $regional->add_entry(new Translation_Entry(['singular' => 'Card number', 'translations' => ['Número regional de prueba']]));
    $regional->add_entry(new Translation_Entry(['singular' => 'The payment could not be prepared.', 'translations' => ['Error regional de prueba.']]));
    wp_mkdir_p(dirname($regionalPath));
    $regional->export_to_file($regionalPath);
    wp_set_current_user(1);
    update_option('woocommerce_y2kwebs_lafise_settings', ['enabled' => 'no']);
    $locales = ['es_CR', 'es_ES', 'es_MX', 'es_AR', 'en_US', 'en_GB'];
    foreach ($locales as $locale) {
        $switched = switch_to_locale($locale);
        $assert($switched || get_locale() === $locale, 'Locale can be selected: ' . $locale);
        $spanish = str_starts_with($locale, 'es_');
        $number = $locale === 'es_AR' ? 'Número regional de prueba' : ($spanish ? 'Número de tarjeta' : 'Card number');
        $assert(__('Card number', 'y2kwebs-lafise') === $number, 'Regional translation or bundled Spanish: ' . $locale . ' actual=' . __('Card number', 'y2kwebs-lafise'));
        $assert(__('Security code', 'y2kwebs-lafise') === ($spanish ? 'Código de seguridad' : 'Security code'), 'Complete fallback with missing regional entries: ' . $locale);
        $gateway = new Gateway();
        $assert($gateway->title === ($spanish ? 'Tarjeta — LAFISE' : 'Card — LAFISE'), 'New default checkout title: ' . $locale);
        $assert(str_contains(Gateway::fields(), $number), 'Classic checkout labels: ' . $locale);
        $blocks = new Blocks(); $blocks->initialize();
        $assert(str_contains($blocks->get_payment_method_data()['fields'], $number), 'Block checkout labels: ' . $locale);
        $messages = Languages::verification();
        $assert($messages['retry'] === ($spanish ? 'Volver a intentar el pago' : 'Try the payment again'), '3DS retry message: ' . $locale);
        $assert($messages['pending'] === ($spanish ? 'El pago está pendiente de comprobación. Contacta con la tienda antes de volver a pagar.' : 'The payment is pending review. Contact the store before paying again.'), 'Uncertain-payment warning: ' . $locale);
        update_option('woocommerce_y2kwebs_lafise_settings', ['title' => 'Mi título personalizado']);
        $assert((new Gateway())->title === 'Mi título personalizado', 'Saved merchant title is never rewritten: ' . $locale);
        update_option('woocommerce_y2kwebs_lafise_settings', ['enabled' => 'no']);
        if ($switched) { restore_previous_locale(); }
    }
    $assert(get_locale() === 'en_US' && __('Security code', 'y2kwebs-lafise') === 'Security code', 'Restoring locale removes Spanish fallback');
    $assert(__('*Synthetic default-domain text*', 'default') === '*Synthetic default-domain text*', 'Other text domains unaffected');

    // Exercise the existing PHP-to-JavaScript configuration without requesting any bank key.
    switch_to_locale('es_CR');
    add_filter('woocommerce_is_checkout', '__return_true');
    Plugin::assets();
    remove_filter('woocommerce_is_checkout', '__return_true');
    $data = wp_scripts()->registered['y2k-lafise-checkout']->extra['data'];
    preg_match('/var y2kLafise = (\{.*\});/s', $data, $matches);
    $config = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    $assert($config['title'] === 'Tarjeta — LAFISE' && str_contains($config['invalidCard'], 'MM/AA'), 'Checkout JavaScript receives translated PHP strings');
    restore_previous_locale();

    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php';
    set_current_screen('dashboard');
    update_user_meta(1, 'locale', 'en_US');
    $storeLocaleFilter = static fn () => 'es_CR';
    add_filter('locale', $storeLocaleFilter);
    $assert(Languages::locale() === 'en_US' && __('Security code', 'y2kwebs-lafise') === 'Security code', 'Administration follows user language, not store language');
    remove_filter('locale', $storeLocaleFilter);
    $storeLocaleFilter = null;
    $GLOBALS['current_screen'] = $oldScreen;

    foreach ([['es_CR', 'en_US', 'No se pudo preparar el pago.'], ['es_AR', 'en_US', 'Error regional de prueba.'], ['en_US', 'es_ES', 'The payment could not be prepared.']] as [$storeLocale, $userLocale, $expected]) {
        update_option('WPLANG', $storeLocale === 'en_US' ? '' : $storeLocale);
        update_user_meta(1, 'locale', $userLocale);
        $cookie = wp_generate_auth_cookie(1, time() + 600, 'logged_in');
        $response = wp_remote_post('http://wordpress/wp-admin/admin-ajax.php', ['timeout' => 15,
            'headers' => ['Host' => 'localhost:8087', 'Cookie' => LOGGED_IN_COOKIE . '=' . $cookie],
            'body' => ['action' => 'y2k_lafise_key', 'csrf' => 'invalid']]);
        $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 400, 'Invalid AJAX payment is rejected without bank HTTP');
        $json = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_THROW_ON_ERROR);
        $assert(($json['data']['message'] ?? '') === $expected, 'Checkout AJAX follows store locale: ' . $storeLocale . '/' . $userLocale . ' actual=' . ($json['data']['message'] ?? 'missing'));
    }
    echo "PASS: $checks language, fallback, merchant-title and AJAX assertions; no real bank requests.\n";
} finally {
    while (is_locale_switched()) { restore_previous_locale(); }
    if ($storeLocaleFilter) { remove_filter('locale', $storeLocaleFilter); }
    $GLOBALS['current_screen'] = $oldScreen;
    update_option('woocommerce_y2kwebs_lafise_settings', $oldSettings);
    update_option('WPLANG', $oldLanguage);
    if ($oldUserLocale === '') { delete_user_meta(1, 'locale'); } else { update_user_meta(1, 'locale', $oldUserLocale); }
    wp_set_current_user($oldUser);
    if ($oldRegional === null) { wp_delete_file($regionalPath); } else { file_put_contents($regionalPath, $oldRegional); }
}
