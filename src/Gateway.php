<?php
namespace Y2KWebs\Lafise;

final class Gateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'y2kwebs_lafise';
        $this->method_title = 'LAFISE — Y2KWebs';
        $this->method_description = __('Acepta pagos con tarjeta mediante LAFISE Costa Rica. Introduce las credenciales de tu comercio y comprueba la conexión.', 'y2kwebs-lafise');
        $this->has_fields = true;
        $this->supports = ['products'];
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', __('Tarjeta — LAFISE', 'y2kwebs-lafise'));
        $this->description = __('Paga con tarjeta. Tu banco puede solicitar una verificación adicional.', 'y2kwebs-lafise');
        $this->enabled = $this->get_option('enabled', 'no');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => ['title' => __('Activar', 'y2kwebs-lafise'), 'type' => 'checkbox', 'default' => 'no',
                'label' => __('Activar pagos con LAFISE', 'y2kwebs-lafise')],
            'title' => ['title' => __('Título', 'y2kwebs-lafise'), 'type' => 'text', 'default' => 'Tarjeta — LAFISE'],
            'mode' => ['title' => __('Modo', 'y2kwebs-lafise'), 'type' => 'select', 'default' => 'live',
                'options' => ['live' => __('Normal: disponible para compradores', 'y2kwebs-lafise'), 'test' => __('Pruebas: solo administradores (cobros reales)', 'y2kwebs-lafise')]],
            'test_max_amount' => ['title' => __('Máximo por pago de prueba', 'y2kwebs-lafise'), 'type' => 'text', 'default' => '1.00',
                'description' => __('Solo se aplica en modo Pruebas, en la moneda del terminal. No es un sandbox.', 'y2kwebs-lafise')],
            'currency' => ['title' => __('Moneda del terminal', 'y2kwebs-lafise'), 'type' => 'select', 'default' => 'USD',
                'options' => ['USD' => 'USD', 'CRC' => 'CRC']],
            'username' => ['title' => 'Username', 'type' => 'text'],
            'password' => ['title' => 'Password', 'type' => 'password',
                'description' => __('Deja vacío para conservar la contraseña guardada.', 'y2kwebs-lafise')],
            'terminal_id' => ['title' => 'TerminalID', 'type' => 'text'],
            'merchant_id' => ['title' => 'MerchantID', 'type' => 'text'],
            'merchant_pay_id' => ['title' => 'API KEY', 'type' => 'password',
                'description' => __('Copia el valor API KEY del correo de LAFISE. Se utiliza para firmar las solicitudes. Deja vacío para conservar la llave guardada.', 'y2kwebs-lafise')],
            'type_request' => ['title' => 'Typerequest', 'type' => 'text', 'default' => 'Production',
                'description' => __('Normalmente Production. Cambia este valor solo si tu configuración bancaria utiliza otro.', 'y2kwebs-lafise')],
            'clear_secrets' => ['title' => __('Eliminar credenciales guardadas', 'y2kwebs-lafise'), 'type' => 'checkbox',
                'label' => __('Borrar Password y API KEY al guardar. Desactiva el método de pago.', 'y2kwebs-lafise'), 'default' => 'no'],
        ];
    }

    public function admin_options(): void
    {
        parent::admin_options();
        echo '<p>' . esc_html__('Las claves se guardan cifradas. Puedes sustituirlas desde estos campos; dejarlos vacíos conserva los valores guardados.', 'y2kwebs-lafise') . '</p>';
        echo '<p>' . esc_html__('Esta versión todavía no envía devoluciones al banco desde WooCommerce. Gestiona la devolución en LAFISE y regístrala aquí como reembolso manual.', 'y2kwebs-lafise') . '</p>';
        echo '<p><a href="https://y2kwebs.com/" target="_blank" rel="noopener noreferrer">' . esc_html__('Instalación y soporte por Y2KWebs', 'y2kwebs-lafise') . '</a></p>';
        echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=y2k_lafise_diagnose'), 'y2k_lafise_diagnose')) . '">' . esc_html__('Comprobar conexión (sin cobro)', 'y2kwebs-lafise') . '</a></p>';
    }

    public function generate_password_html($key, $data): string
    {
        $id = $this->get_field_key($key);
        $configured = (new Config())->get($key) !== '';
        return '<tr valign="top"><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($data['title'])
            . '</label></th><td><input class="input-text regular-input" type="password" autocomplete="new-password" id="'
            . esc_attr($id) . '" name="' . esc_attr($id) . '" value="" placeholder="'
            . esc_attr($configured ? __('Guardada — deja vacío para conservar', 'y2kwebs-lafise') : __('Sin configurar', 'y2kwebs-lafise'))
            . '"><p class="description">' . esc_html($data['description'] ?? '') . '</p></td></tr>';
    }

    public function validate_password_field($key, $value): string
    {
        $value = is_string($value) ? wp_unslash($value) : '';
        if (strlen($value) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new \Exception(__('Credencial no válida.', 'y2kwebs-lafise'));
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
        echo self::fields(); // All markup is constant; translated labels escaped within fields().
    }

    public static function fields(): string
    {
        $fields = [
            ['number', __('Número de tarjeta', 'y2kwebs-lafise'), 'cc-number', 'numeric', '23'],
            ['name', __('Nombre en la tarjeta', 'y2kwebs-lafise'), 'cc-name', 'text', '100'],
            ['expiry', __('Caducidad (MM/AA)', 'y2kwebs-lafise'), 'cc-exp', 'numeric', '5'],
            ['cvv', __('Código de seguridad', 'y2kwebs-lafise'), 'cc-csc', 'numeric', '4'],
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
                $input[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            }
            $attempt = (new Payments())->start($order, $input);
            return ['result' => 'success', 'redirect' => Payments::url('verify', $attempt)];
        } catch (\Throwable) {
            wc_add_notice(__('No se pudo completar el pago. Revisa el estado del pedido antes de volver a intentarlo.', 'y2kwebs-lafise'), 'error');
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
