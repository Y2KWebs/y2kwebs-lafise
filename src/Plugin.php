<?php
namespace Y2KWebs\Lafise;

final class Plugin
{
    public static function activate(): void
    {
        if (PHP_VERSION_ID < 80200 || !extension_loaded('openssl') || !extension_loaded('dom') || !class_exists('WooCommerce')) {
            wp_die(esc_html__('Requiere WooCommerce, PHP 8.2+, OpenSSL y DOM.', 'y2kwebs-lafise'));
        }
        Store::install();
        self::routes();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        wp_unschedule_hook('y2k_lafise_reconcile');
        flush_rewrite_rules(false);
    }

    public static function boot(): void
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        add_filter('woocommerce_payment_gateways', static fn ($gateways) => [...$gateways, Gateway::class]);
        add_filter('pre_update_option_' . Settings::OPTION, [Settings::class, 'protect'], 10, 2);
        add_filter('pre_add_option_' . Settings::OPTION, [Settings::class, 'protect']);
        add_action('rest_api_init', [Settings::class, 'routes']);
        add_action('init', [self::class, 'routes']);
        add_filter('query_vars', static fn ($vars) => [...$vars, 'y2k_action', 'y2k_reference', 'y2k_token']);
        add_action('template_redirect', [Routes::class, 'handle'], 0);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('wp_ajax_y2k_lafise_key', [Routes::class, 'key']);
        add_action('wp_ajax_nopriv_y2k_lafise_key', [Routes::class, 'key']);
        add_action('y2k_lafise_reconcile', static function ($reference): void {
            try { (new Payments())->reconcile($reference); } catch (\Throwable) { /* No payload logging. */ }
        });
        $registerBlocks = static function (): void {
            add_action('woocommerce_blocks_payment_method_type_registration', static fn ($registry) => $registry->register(new Blocks()));
        };
        if (did_action('woocommerce_blocks_loaded')) {
            $registerBlocks();
        } else {
            add_action('woocommerce_blocks_loaded', $registerBlocks);
        }
        add_action('admin_post_y2k_lafise_diagnose', [Routes::class, 'diagnose']);
        add_filter('woocommerce_order_actions', static function ($actions, $order) {
            if ($order && $order->get_payment_method() === 'y2kwebs_lafise') {
                $actions['y2k_lafise_check'] = __('Consultar estado en LAFISE (sin nuevo cobro)', 'y2kwebs-lafise');
            }
            return $actions;
        }, 10, 2);
        add_action('woocommerce_order_action_y2k_lafise_check', static function ($order): void {
            if (!current_user_can('edit_shop_orders')) {
                return;
            }
            try {
                (new Payments())->reconcile((string) $order->get_meta('_y2k_lafise_reference'));
            } catch (\Throwable) {
                $order->add_order_note(__('LAFISE: no se pudo consultar. Revisar manualmente; no repetir el cobro.', 'y2kwebs-lafise'));
            }
        });
    }

    public static function routes(): void
    {
        add_rewrite_rule('^y2k-lafise/(verify|status|callback)/(Y2K[A-F0-9]{16})/([a-f0-9]{64})/?$',
            'index.php?y2k_action=$matches[1]&y2k_reference=$matches[2]&y2k_token=$matches[3]', 'top');
    }

    public static function assets(): void
    {
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        Payments::session();
        WC()->session->set_customer_session_cookie(true);
        wp_enqueue_style('y2k-lafise', plugins_url('assets/checkout.css', Y2K_LAFISE_FILE), [], Y2K_LAFISE_VERSION);
        wp_enqueue_script('y2k-lafise-checkout', plugins_url('assets/checkout.js', Y2K_LAFISE_FILE), ['jquery'], Y2K_LAFISE_VERSION, true);
        wp_localize_script('y2k-lafise-checkout', 'y2kLafise', ['ajax' => admin_url('admin-ajax.php'),
            'csrf' => Payments::csrf(), 'jwe' => plugins_url('assets/jwe.mjs', Y2K_LAFISE_FILE),
            'error' => __('No se pudo preparar el pago. Comprueba los datos e inténtalo nuevamente.', 'y2kwebs-lafise')]);
    }
}
