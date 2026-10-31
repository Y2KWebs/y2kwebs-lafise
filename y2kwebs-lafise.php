<?php
/**
 * Plugin Name: Y2KWebs Gateway for LAFISE
 * Description: Pagos con tarjeta LAFISE Costa Rica para WooCommerce. Configura las credenciales de tu comercio desde el administrador.
 * Version: 0.2.1
 * Author: Y2KWebs
 * Author URI: https://y2kwebs.com
 * License: GPL-2.0-or-later
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 10.3
 * Text Domain: y2kwebs-lafise
 */
defined('ABSPATH') || exit;
define('Y2K_LAFISE_VERSION', '0.2.1');
define('Y2K_LAFISE_FILE', __FILE__);
spl_autoload_register(static function (string $class): void {
    $prefix = 'Y2KWebs\\Lafise\\';
    if (str_starts_with($class, $prefix)) {
        $name = substr($class, strlen($prefix));
        if (preg_match('/^[A-Za-z]+$/D', $name) && is_file(__DIR__ . '/src/' . $name . '.php')) {
            require_once __DIR__ . '/src/' . $name . '.php';
        }
    }
});
register_activation_hook(__FILE__, [Y2KWebs\Lafise\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Y2KWebs\Lafise\Plugin::class, 'deactivate']);
add_action('plugins_loaded', [Y2KWebs\Lafise\Plugin::class, 'boot']);
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
