<?php
namespace Y2KWebs\Lafise;

final class Settings
{
    public const OPTION = 'woocommerce_y2kwebs_lafise_settings';

    /** Applies to both native WooCommerce admin and API writes; never keep secrets in gateway settings. */
    public static function protect(mixed $new, mixed $old = []): mixed
    {
        if (!is_array($new)) { return $old; }
        $clear = ($new['clear_secrets'] ?? '') === 'yes';
        $incoming = [];
        foreach (['password', 'merchant_pay_id'] as $key) {
            $value = $new[$key] ?? '';
            if (is_string($value) && $value !== '' && strlen($value) <= 4096 && !preg_match('/[\x00-\x1f\x7f]/', $value)) {
                $incoming[$key] = $value;
            }
            $new[$key] = '';
        }
        if ($clear) {
            delete_option('y2k_lafise_secrets');
            $new['enabled'] = 'no';
        } elseif ($incoming) {
            $existing = get_option('y2k_lafise_secrets', '');
            try { $secrets = $existing ? Secrets::open($existing) : []; } catch (\Throwable) { $secrets = []; }
            update_option('y2k_lafise_secrets', Secrets::seal(array_merge($secrets, $incoming)), false);
        }
        $new['clear_secrets'] = 'no';
        return $new;
    }

    public static function routes(): void
    {
        register_rest_route('y2k-lafise/v1', '/settings', [
            'methods' => ['GET', 'POST'],
            'permission_callback' => static fn () => current_user_can('manage_woocommerce'),
            'callback' => [self::class, 'api'],
        ]);
        register_rest_route('y2k-lafise/v1', '/attempts/(?P<reference>Y2K[A-F0-9]{16})', [
            'methods' => 'GET',
            'permission_callback' => static fn () => current_user_can('manage_woocommerce'),
            'callback' => static function (\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
                $attempt = (new Store())->find($request['reference']);
                if (!$attempt) { return new \WP_Error('attempt_not_found', 'Attempt not found.', ['status' => 404]); }
                return new \WP_REST_Response(['order_id' => (int) $attempt['order_id'], 'state' => $attempt['state'],
                    'reason' => $attempt['data']['reason'] ?? null, 'diagnostic' => $attempt['data']['diagnostic'] ?? null,
                    'receipt_available' => !empty($attempt['data']['receipt']),
                    'bank_reference_available' => !empty($attempt['data']['ref3']) && !empty($attempt['data']['receipt']['bank_reference'])]);
            },
        ]);
    }

    public static function api(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if ($request->get_method() === 'POST') {
            $input = $request->get_json_params();
            if (!is_array($input)) { return new \WP_Error('invalid_settings', 'JSON object required.', ['status' => 400]); }
            $fields = (new Gateway())->form_fields;
            $settings = (array) get_option(self::OPTION, []);
            foreach ($input as $key => $value) {
                if (!isset($fields[$key]) || !is_string($value) || strlen($value) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                    return new \WP_Error('invalid_settings', 'Invalid setting.', ['status' => 400]);
                }
                if (isset($fields[$key]['options']) && !array_key_exists($value, $fields[$key]['options'])) {
                    return new \WP_Error('invalid_settings', 'Invalid choice.', ['status' => 400]);
                }
                if (in_array($key, ['enabled','clear_secrets'], true) && !in_array($value, ['yes','no'], true)) {
                    return new \WP_Error('invalid_settings', 'Invalid switch.', ['status' => 400]);
                }
                $settings[$key] = in_array($key,['password','merchant_pay_id'],true) ? $value : sanitize_text_field($value);
            }
            update_option(self::OPTION, $settings, false);
        }
        $config = new Config();
        $result = (array) get_option(self::OPTION, []);
        unset($result['password'], $result['merchant_pay_id']);
        $result['password_configured'] = $config->get('password') !== '';
        $result['merchant_pay_id_configured'] = $config->get('merchant_pay_id') !== '';
        return new \WP_REST_Response($result);
    }
}
