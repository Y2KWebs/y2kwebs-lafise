<?php
namespace Y2KWebs\Lafise;

final class Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    protected $name = 'y2kwebs_lafise';
    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_y2kwebs_lafise_settings', []);
    }
    public function is_active(): bool
    {
        return (new Config())->allowed();
    }
    public function get_payment_method_script_handles(): array
    {
        Plugin::assets();
        wp_register_script('y2k-lafise-blocks', plugins_url('assets/blocks.js', Y2K_LAFISE_FILE),
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'y2k-lafise-checkout'], Y2K_LAFISE_VERSION, true);
        return ['y2k-lafise-blocks'];
    }
    public function get_payment_method_data(): array
    {
        return ['title' => $this->get_setting('title', 'Tarjeta — LAFISE'), 'supports' => ['products'], 'fields' => Gateway::fields()];
    }
}
