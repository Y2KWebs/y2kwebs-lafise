<?php
namespace Y2KWebs\Lafise;

final class Config
{
    private array $settings;
    private array $private;

    public function __construct()
    {
        // Match the admin form default even when settings were saved partially through the API.
        $this->settings = array_replace(['type_request' => 'Production'], (array) get_option('woocommerce_y2kwebs_lafise_settings', []));
        $this->private = defined('Y2K_LAFISE_PRIVATE') && is_array(Y2K_LAFISE_PRIVATE) ? Y2K_LAFISE_PRIVATE : [];
    }

    public function get(string $key, mixed $default = ''): mixed
    {
        if (array_key_exists($key, $this->private)) {
            return $this->private[$key];
        }
        if (in_array($key, ['password', 'merchant_pay_id'], true)) {
            $stored = get_option('y2k_lafise_secrets', '');
            if (!$stored) { return $default; }
            try { return Secrets::open($stored)[$key] ?? $default; } catch (\Throwable) { return $default; }
        }
        return $this->private[$key] ?? $this->settings[$key] ?? $default;
    }

    public function allowed(): bool
    {
        if ($this->get('enabled', 'no') !== 'yes' || !is_ssl()) {
            return false;
        }
        foreach (['username', 'password', 'merchant_pay_id', 'merchant_id', 'terminal_id', 'type_request'] as $key) {
            if (!is_string($this->get($key)) || $this->get($key) === '') {
                return false;
            }
        }
        if (!in_array($this->get('currency'), ['USD', 'CRC'], true) || get_woocommerce_currency() !== $this->get('currency')) {
            return false;
        }
        if ($this->restricted()) {
            return (current_user_can('manage_woocommerce') || current_user_can('y2k_lafise_test')) && $this->limit() > 0;
        }
        return $this->get('mode', 'live') === 'live';
    }

    public function restricted(): bool
    {
        return in_array($this->get('mode', 'live'), ['test', 'pilot'], true);
    }

    public function limit(): int
    {
        if (isset($this->private['pilot_limit_minor'])) { return (int) $this->private['pilot_limit_minor']; }
        try { return Money::minor((string) $this->get('test_max_amount', '1.00')); } catch (\Throwable) { return 0; }
    }

    public function guardAmount(int $minor, int $tax): void
    {
        if (!$this->allowed() || $minor <= 0 || $tax < 0 || $tax > $minor) {
            throw new \DomainException('Payment configuration is not available for this order.');
        }
        if ($this->restricted() && $minor > $this->limit()) {
            throw new \DomainException('Pilot amount limit exceeded.');
        }
    }

    public function context(): array
    {
        return ['user_name' => $this->get('username'), 'secret_key' => $this->get('password'),
            'merchant_pay_id' => $this->get('merchant_pay_id'), 'merchant_id' => $this->get('merchant_id'),
            'terminal_id' => $this->get('terminal_id')];
    }
}
