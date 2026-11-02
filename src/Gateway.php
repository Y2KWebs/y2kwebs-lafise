<?php
namespace Y2KWebs\Lafise;

final class Gateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'y2kwebs_lafise';
        $this->method_title = 'LAFISE — Y2KWebs';
        $this->method_description = __('Accept card payments through LAFISE Costa Rica. Enter your merchant credentials and check the connection.', 'y2kwebs-lafise');
        $this->has_fields = true;
        $this->supports = ['products'];
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', __('Card — LAFISE', 'y2kwebs-lafise'));
        $this->description = __('Pay by card. Your bank may request additional verification.', 'y2kwebs-lafise');
        $this->enabled = $this->get_option('enabled', 'no');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => ['title' => __('Enable', 'y2kwebs-lafise'), 'type' => 'checkbox', 'default' => 'no',
                'label' => __('Enable LAFISE payments', 'y2kwebs-lafise')],
            'title' => ['title' => __('Title', 'y2kwebs-lafise'), 'type' => 'text', 'default' => __('Card — LAFISE', 'y2kwebs-lafise')],
            'mode' => ['title' => __('Mode', 'y2kwebs-lafise'), 'type' => 'select', 'default' => 'live',
                'options' => ['live' => __('Normal: available to buyers', 'y2kwebs-lafise'), 'test' => __('Testing: administrators only (real charges)', 'y2kwebs-lafise')]],
            'test_max_amount' => ['title' => __('Maximum test payment amount', 'y2kwebs-lafise'), 'type' => 'text', 'default' => '1.00',
                'description' => __('Applies only in Testing mode, in the terminal currency. This is not a sandbox.', 'y2kwebs-lafise')],
            'currency' => ['title' => __('Terminal currency', 'y2kwebs-lafise'), 'type' => 'select', 'default' => 'USD',
                'options' => ['USD' => 'USD', 'CRC' => 'CRC']],
            'username' => ['title' => 'Username', 'type' => 'text'],
            'password' => ['title' => 'Password', 'type' => 'password',
                'description' => __('Leave blank to keep the saved password.', 'y2kwebs-lafise')],
            'terminal_id' => ['title' => 'TerminalID', 'type' => 'text'],
            'merchant_id' => ['title' => 'MerchantID', 'type' => 'text'],
            'merchant_pay_id' => ['title' => 'API KEY', 'type' => 'password',
                'description' => __('Copy the API KEY value from the LAFISE email. It is used to sign requests. Leave blank to keep the saved key.', 'y2kwebs-lafise')],
            'type_request' => ['title' => 'Typerequest', 'type' => 'text', 'default' => 'Production',
                'description' => __('Usually Production. Change this value only if your bank configuration uses another value.', 'y2kwebs-lafise')],
            'clear_secrets' => ['title' => __('Delete saved credentials', 'y2kwebs-lafise'), 'type' => 'checkbox',
                'label' => __('Delete Password and API KEY when saving. This disables the payment method.', 'y2kwebs-lafise'), 'default' => 'no'],
        ];
    }

    public function admin_options(): void
    {
        parent::admin_options();
        echo '<p>' . esc_html__('Keys are stored encrypted. You can replace them using these fields; leaving them blank keeps the saved values.', 'y2kwebs-lafise') . '</p>';
        echo '<p>' . esc_html__('This version does not yet send bank refunds from WooCommerce. Handle the refund through LAFISE and record it here as a manual refund.', 'y2kwebs-lafise') . '</p>';
        echo '<p><a href="https://y2kwebs.com/" target="_blank" rel="noopener noreferrer">' . esc_html__('Installation and support by Y2KWebs', 'y2kwebs-lafise') . '</a></p>';
        echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=y2k_lafise_diagnose'), 'y2k_lafise_diagnose')) . '">' . esc_html__('Check connection (no charge)', 'y2kwebs-lafise') . '</a></p>';
    }

    public function generate_password_html($key, $data): string
    {
        $id = $this->get_field_key($key);
        $configured = (new Config())->get($key) !== '';
        return '<tr valign="top"><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($data['title'])
            . '</label></th><td><input class="input-text regular-input" type="password" autocomplete="new-password" id="'
            . esc_attr($id) . '" name="' . esc_attr($id) . '" value="" placeholder="'
            . esc_attr($configured ? __('Saved — leave blank to keep', 'y2kwebs-lafise') : __('Not configured', 'y2kwebs-lafise'))
            . '"><p class="description">' . esc_html($data['description'] ?? '') . '</p></td></tr>';
    }

    public function validate_password_field($key, $value): string
    {
        $value = is_string($value) ? wp_unslash($value) : '';
        if (strlen($value) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new \Exception(esc_html__('Invalid credential.', 'y2kwebs-lafise'));
        }
        return $value;
    }

    public function is_available(): bool
    {
        return parent::is_available() && (new Config())->allowed();
    }

    public function payment_fields(): void
    {
        echo '<p>' . esc_html($this->description) . '</p>';
        echo wp_kses(self::fields(), self::fieldTags());
    }

    public static function fieldTags(): array
    {
        return ['div' => ['class' => true, 'data-y2k-card' => true], 'label' => [],
            'input' => ['data-card' => true, 'type' => true, 'autocomplete' => true, 'inputmode' => true, 'maxlength' => true],
            'p' => ['data-y2k-error' => true, 'role' => true]];
    }

    public static function fields(): string
    {
        $fields = [
            ['number', __('Card number', 'y2kwebs-lafise'), 'cc-number', 'numeric', '23'],
            ['name', __('Name on card', 'y2kwebs-lafise'), 'cc-name', 'text', '100'],
            ['expiry', __('Expiry (MM/YY)', 'y2kwebs-lafise'), 'cc-exp', 'numeric', '5'],
            ['cvv', __('Security code', 'y2kwebs-lafise'), 'cc-csc', 'numeric', '4'],
        ];
        $html = '<div class="y2k-card" data-y2k-card>';
        foreach ($fields as [$key, $label, $autocomplete, $mode, $length]) {
            // Intentionally no name attribute: plaintext card data cannot enter WooCommerce form serialization.
            $html .= '<label>' . esc_html($label) . '<input data-card="' . esc_attr($key) . '" type="' . ($key === 'cvv' ? 'password' : 'text')
                . '" autocomplete="' . esc_attr($autocomplete) . '" inputmode="' . esc_attr($mode) . '" maxlength="' . esc_attr($length) . '"></label>';
        }
        return $html . '<p data-y2k-error role="alert"></p></div>';
    }

    public function process_payment($order_id): array
    {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new \DomainException('Missing order.');
            }
            $input = [];
            foreach (['y2k_card', 'y2k_ticket', 'y2k_csrf', 'y2k_width', 'y2k_height'] as $key) {
                $input[$key] = Routes::post($key);
            }
            $attempt = (new Payments())->start($order, $input);
            return ['result' => 'success', 'redirect' => Payments::url('verify', $attempt)];
        } catch (\Throwable) {
            wc_add_notice(__('The payment could not be completed. Check the order status before trying again.', 'y2kwebs-lafise'), 'error');
            return ['result' => 'failure'];
        } finally {
            unset($_POST['y2k_card'], $_POST['y2k_ticket']);
        }
    }

    public function process_refund($order_id, $amount = null, $reason = ''): \WP_Error|bool
    {
        return (new Refunds())->process((int) $order_id, $amount, (string) $reason);
    }
}
