<?php
namespace Y2KWebs\Lafise;

final class Routes
{
    /** Read only the restricted token/JWE/base64 alphabets without changing signed bytes. */
    public static function post(string $name): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checkout/AJAX use Payments::verifyCsrf; bank callbacks use a bearer token and 3DS correlation.
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) { return ''; }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Authentication is enforced by the caller before payment processing.
        $value = sanitize_text_field(wp_unslash($_POST[$name]));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The raw value is used only to reject any sanitization change to signed bytes, never for processing or output.
        if ($value !== wp_unslash($_POST[$name]) || strlen($value) > 16384 || !preg_match('/^[A-Za-z0-9_+\/=.-]*$/D', $value)) {
            throw new \DomainException('Invalid payment input.');
        }
        return $value;
    }

    private static function method(): string
    {
        return strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')));
    }

    public static function key(): void
    {
        nocache_headers();
        try {
            if (self::method() !== 'POST' || !is_ssl()) {
                throw new \DomainException('Invalid request.');
            }
            $csrf = self::post('csrf');
            wp_send_json_success((new Payments())->key($csrf));
        } catch (\Throwable) {
            wp_send_json_error(['message' => __('No se pudo preparar el pago.', 'y2kwebs-lafise')], 400);
        }
    }

    public static function diagnose(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Acceso denegado.', 'y2kwebs-lafise'), '', ['response' => 403]);
        }
        check_admin_referer('y2k_lafise_diagnose');
        $ok = false;
        try {
            (new Bank(new Config()))->authenticate();
            $ok = true;
        } catch (\Throwable) {}
        wp_die(esc_html($ok ? __('Conexión y llave pública válidas. No se ha realizado ningún cobro.', 'y2kwebs-lafise')
            : __('No se pudo validar la conexión. Revisa la configuración privada.', 'y2kwebs-lafise')), 'LAFISE', ['response' => $ok ? 200 : 400, 'back_link' => true]);
    }

    public static function handle(): void
    {
        $action = get_query_var('y2k_action');
        if (!$action) { return; }
        if (!defined('DONOTCACHEPAGE')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin interoperability flag; its name must not change.
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; frame-src https:; form-action https:; base-uri 'none'; object-src 'none'; frame-ancestors 'self'");
        try {
            if (!is_ssl()) { throw new \DomainException('HTTPS required.'); }
            $reference = (string) get_query_var('y2k_reference');
            $token = (string) get_query_var('y2k_token');
            $payments = new Payments();
            $a = $payments->authorize($reference, $token);
            if ($action === 'callback') {
                if (self::method() !== 'POST' || absint(wp_unslash($_SERVER['CONTENT_LENGTH'] ?? 0)) > 65536) {
                    throw new \DomainException('Invalid callback.');
                }
                $input = [];
                foreach (['threeDSMethodData', 'threeDSSessionData', 'cres'] as $name) {
                    $input[$name] = self::post($name);
                }
                $payments->callback($reference, $token, $input);
                self::page(__('Verificación recibida. Puedes regresar al pago.', 'y2kwebs-lafise'), 'callback.js');
            }
            if (self::method() !== 'GET') { throw new \DomainException('Invalid request.'); }
            if ($action === 'status') {
                wp_send_json($payments->status($reference, $token));
            }
            if ($action === 'verify') {
                $body = '<main class="y2k-verify" data-status-url="' . esc_url(Payments::url('status', $a)) . '">'
                    . '<h1>' . esc_html__('Confirmación del pago', 'y2kwebs-lafise') . '</h1>'
                    . '<p role="status" data-message>' . esc_html__('Consultando el resultado…', 'y2kwebs-lafise') . '</p>'
                    . '<iframe hidden name="y2k-bank" title="' . esc_attr__('Verificación de tu banco', 'y2kwebs-lafise') . '" sandbox="allow-forms allow-scripts allow-same-origin allow-top-navigation-by-user-activation"></iframe>'
                    . '<p>' . esc_html__('No cierres esta página mientras verificas el pago. Si el resultado queda pendiente, contacta con la tienda antes de pagar de nuevo.', 'y2kwebs-lafise') . '</p></main>';
                self::page($body, 'verify.js', true);
            }
            throw new \DomainException('Invalid route.');
        } catch (\Throwable) {
            status_header(400);
            self::page(__('No se pudo completar esta consulta. Contacta con la tienda antes de repetir el pago.', 'y2kwebs-lafise'));
        }
    }

    private static function page(string $body, string $script = '', bool $html = false): never
    {
        wp_enqueue_style('y2k-lafise-page', plugins_url('assets/checkout.css', Y2K_LAFISE_FILE), [], Y2K_LAFISE_VERSION);
        if ($script) {
            wp_enqueue_script('y2k-lafise-page', plugins_url('assets/' . $script, Y2K_LAFISE_FILE), [], Y2K_LAFISE_VERSION, ['strategy' => 'defer']);
        }
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . esc_html__('Pago LAFISE', 'y2kwebs-lafise') . '</title>';
        wp_print_styles(['y2k-lafise-page']);
        echo '</head><body>';
        if ($html) {
            echo wp_kses($body, ['main' => ['class' => true, 'data-status-url' => true], 'h1' => [],
                'p' => ['role' => true, 'data-message' => true],
                'iframe' => ['hidden' => true, 'name' => true, 'title' => true, 'sandbox' => true]]);
        } else {
            echo '<p>' . esc_html($body) . '</p>';
        }
        if ($script) { wp_print_scripts(['y2k-lafise-page']); }
        echo '</body></html>';
        exit;
    }
}
