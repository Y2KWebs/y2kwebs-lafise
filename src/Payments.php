<?php
namespace Y2KWebs\Lafise;

final class Payments
{
    public function __construct(private Config $config = new Config(), private Store $store = new Store()) {}

    public static function session(): void
    {
        if (!WC()->session) {
            WC()->initialize_session();
        }
    }

    public static function csrf(): string
    {
        self::session();
        if (!WC()->session->get('y2k_csrf')) {
            WC()->session->set('y2k_csrf', bin2hex(random_bytes(32)));
        }
        return WC()->session->get('y2k_csrf');
    }

    public static function verifyCsrf(string $value): void
    {
        if (!$value || !hash_equals(self::csrf(), $value)) {
            throw new \DomainException('Session expired.');
        }
    }

    public function key(string $csrf): array
    {
        self::verifyCsrf($csrf);
        if (!$this->config->allowed()) {
            throw new \DomainException('Gateway unavailable.');
        }
        $last = (int) WC()->session->get('y2k_key_at', 0);
        if (time() - $last < 5) {
            throw new \DomainException('Please wait before retrying.');
        }
        WC()->session->set('y2k_key_at', time());
        $auth = (new Bank($this->config))->authenticate();
        $ticket = bin2hex(random_bytes(24));
        WC()->session->set('y2k_key', Secrets::seal(['ticket' => $ticket, 'auth' => $auth, 'expires' => time() + 300]));
        return ['jwk' => $auth['jwk'], 'ticket' => $ticket];
    }

    public function start(\WC_Order $order, array $input): array
    {
        self::verifyCsrf((string) ($input['y2k_csrf'] ?? ''));
        return $this->store->locked($order->get_id(), function () use ($order, $input): array {
            if ($order->is_paid() || $order->get_payment_method() !== 'y2kwebs_lafise') {
                throw new \DomainException('Order is not payable.');
            }
            $existing = $this->store->latest($order->get_id());
            if ($existing) {
                $existing = $this->expireAuthentication($existing);
                $order = wc_get_order($order->get_id());
            }
            if ($existing && $existing['state'] !== 'declined') {
                // A duplicate must not get a bearer URL unless it belongs to the original checkout session.
                if (!hash_equals($existing['data']['session'], hash('sha256', self::csrf()))) {
                    throw new \DomainException('Payment requires review.');
                }
                return $existing;
            }
            if (!$order->needs_payment()) { throw new \DomainException('Order is not payable.'); }
            $amount = Money::minor((string) $order->get_total());
            $tax = Money::minor((string) $order->get_total_tax());
            $this->config->guardAmount($amount, $tax);
            if ($order->get_currency() !== $this->config->get('currency')) {
                throw new \DomainException('Unsupported currency.');
            }
            $key = Secrets::open((string) WC()->session->get('y2k_key', ''));
            if ($key['expires'] < time() || !hash_equals($key['ticket'], (string) ($input['y2k_ticket'] ?? ''))) {
                throw new \DomainException('Transport key expired.');
            }
            $encrypted = $input['y2k_card'] ?? '';
            if (!is_string($encrypted) || strlen($encrypted) > 8192 || !preg_match('/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+){4}$/D', $encrypted)) {
                throw new \DomainException('Invalid encrypted card.');
            }
            $token = bin2hex(random_bytes(32));
            $data = ['amount' => $amount, 'tax' => $tax, 'currency' => $order->get_currency(),
                'merchant_id' => $this->config->get('merchant_id'), 'terminal_id' => $this->config->get('terminal_id'),
                'token' => $token, 'session' => hash('sha256', self::csrf()), 'expires' => time() + 1800,
                'steps' => [], 'refund_state' => 'none', 'ticket_hash' => hash('sha256', $key['ticket'])];
            $attempt = $this->store->create($order->get_id(), $data);
            wp_schedule_single_event(time() + 120, 'y2k_lafise_reconcile', [$attempt['reference']]);
            // Persist the irreversible intent before HTTP; crashes cannot permit an automatic repeat.
            $order->update_meta_data('_y2k_lafise_reference', $attempt['reference']);
            $order->set_status('on-hold');
            $order->save();
            WC()->session->__unset('y2k_key');
            WC()->session->save_data();
            $context = $this->context($attempt) + [
                'description' => 'WooCommerce ' . $order->get_id(), 'request_date' => wp_date('dmYHis'),
                'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name(),
                'phone' => $order->get_billing_phone(), 'email' => $order->get_billing_email(),
                'country' => $order->get_billing_country(), 'payer_ip' => \WC_Geolocation::get_ip_address(),
                'response_url' => self::url('callback', $attempt), 'encrypted_card' => $encrypted,
                'type_request' => $this->config->get('type_request'), 'payment_type' => 4,
                'screen_width' => (string) max(1, min(9999, (int) ($input['y2k_width'] ?? 1))),
                'screen_height' => (string) max(1, min(9999, (int) ($input['y2k_height'] ?? 1)))];
            $result = (new Bank($this->config))->execute('sale', $context, [], $key['auth']);
            return $this->apply($attempt, $result);
        });
    }

    public function callback(string $reference, string $token, array $input): void
    {
        $initial = $this->authorize($reference, $token);
        $this->store->locked((int) $initial['order_id'], function () use ($reference, $token, $input): void {
            $a = $this->authorize($reference, $token);
            $a = $this->expireAuthentication($a);
            if (!in_array($a['state'], ['fingerprint', 'challenge'], true) || $a['data']['expires'] < time()) {
                return;
            }
            $fields = ThreeDS::callback($input, $a['state'], $a['data']['transaction_id']);
            $canonical = array_map(static fn ($v) => $v === '' ? '' : base64_encode(ThreeDS::decode($v)), $fields);
            $digest = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
            if (in_array($digest, $a['data']['steps'], true) || count($a['data']['steps']) >= 4) {
                return;
            }
            $data = $a['data'];
            $data['steps'][] = $digest;
            unset($data['action']);
            $a = $this->store->save($a, 'processing', $data);
            $result = (new Bank($this->config))->execute('process', $this->context($a) + $fields, $data);
            $this->apply($a, $result);
        });
    }

    private function context(array $a): array
    {
        $d = $a['data'];
        if ($d['merchant_id'] !== $this->config->get('merchant_id') || $d['terminal_id'] !== $this->config->get('terminal_id')
            || $d['currency'] !== $this->config->get('currency')) {
            throw new \DomainException('Gateway account changed; manual review required.');
        }
        return ['amount' => (string) $d['amount'], 'tax' => (string) $d['tax'], 'currency' => $d['currency'],
            'reference' => $a['reference'], 'ref3' => $d['ref3'] ?? ''];
    }

    private function apply(array $a, array $result): array
    {
        $state = $result['state'];
        $data = array_merge($a['data'], $result);
        unset($data['state']);
        if (!in_array($state, ['fingerprint', 'challenge'], true)) {
            unset($data['action']);
        } else {
            $data['auth_expires'] = min(time() + 600, $data['expires']);
        }
        $a = $this->store->save($a, $state, $data);
        $this->syncOrder($a);
        if (in_array($state, ['unknown', 'fingerprint', 'challenge'], true)) {
            wp_schedule_single_event($data['auth_expires'] ?? time() + 120, 'y2k_lafise_reconcile', [$a['reference']]);
        }
        return $a;
    }

    public function syncOrder(array $a): void
    {
        $order = wc_get_order($a['order_id']);
        if (!$order || $order->get_payment_method() !== 'y2kwebs_lafise') {
            throw new \DomainException('Invalid order.');
        }
        if ($a['state'] === 'approved' && !$order->is_paid()) {
            if (Money::minor((string) $order->get_total()) !== $a['data']['amount'] || $order->get_currency() !== $a['data']['currency']) {
                throw new \DomainException('Order changed after payment.');
            }
            $order->payment_complete($a['data']['receipt']['bank_reference']);
            $order->add_order_note(__('Pago LAFISE confirmado.', 'y2kwebs-lafise'));
        } elseif ($a['state'] === 'unknown' && !$order->is_paid() && !$order->get_meta('_y2k_lafise_review')) {
            $order->update_meta_data('_y2k_lafise_review', true);
            $order->update_status('on-hold');
            $order->add_order_note(__('LAFISE: resultado pendiente de comprobación. No repetir el cobro.', 'y2kwebs-lafise'));
            $order->save();
        } elseif ($a['state'] === 'declined' && !$order->is_paid()) {
            $reason = $a['data']['reason'] ?? '';
            $order->update_status('failed', in_array($reason, ['authentication_expired','authentication_setup_failed','payment_not_sent'], true)
                ? __('LAFISE: no se completó la autenticación; puedes volver a intentar el pago.', 'y2kwebs-lafise')
                : __('LAFISE: pago rechazado.', 'y2kwebs-lafise'));
        }
    }

    public function reconcile(string $reference): void
    {
        $a = $this->store->find($reference);
        if (!$a) {
            return;
        }
        $this->store->locked((int) $a['order_id'], function () use ($reference): void {
            $a = $this->store->find($reference);
            $a = $this->expireAuthentication($a);
            if ($a['state'] === 'approved') {
                $this->syncOrder($a);
                return;
            }
            if (in_array($a['state'], ['fingerprint', 'challenge'], true) && ($a['data']['auth_expires'] ?? $a['data']['expires']) >= time()) {
                wp_schedule_single_event(($a['data']['auth_expires'] ?? $a['data']['expires']) + 1, 'y2k_lafise_reconcile', [$reference]);
                return;
            }
            if ($a['state'] === 'declined') {
                return;
            }
            $last = $a['data']['queried_at'] ?? 0;
            if (time() - $last < 60) {
                return;
            }
            $data = $a['data'];
            $data['queried_at'] = time();
            $a = $this->store->save($a, 'unknown', $data);
            $result = !empty($data['ref3']) && !empty($data['receipt']['bank_reference'])
                ? (new Bank($this->config))->execute('inquire', $this->context($a), $data)
                : ['state' => 'unknown', 'reason' => 'manual_review_required'];
            // Bounded recovery: automatic polling never resends Sale or Process3DS.
            $a = $this->store->save($a, $result['state'], array_merge($data, $result));
            $this->syncOrder($a);
        });
    }

    public function authorize(string $reference, string $token): array
    {
        $a = $this->store->find($reference);
        if (!$a || !preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($a['data']['token'], $token)) {
            throw new \DomainException('Invalid payment link.');
        }
        return $a;
    }

    public static function url(string $action, array $a): string
    {
        return home_url('/y2k-lafise/' . $action . '/' . rawurlencode($a['reference']) . '/' . $a['data']['token'] . '/');
    }

    public function status(string $reference, string $token): array
    {
        $a = $this->authorize($reference, $token);
        if (in_array($a['state'], ['fingerprint', 'challenge'], true) && ($a['data']['auth_expires'] ?? $a['data']['expires']) < time()) {
            $a = $this->store->locked((int) $a['order_id'], fn () => $this->expireAuthentication($this->authorize($reference, $token)));
        }
        if ($a['data']['expires'] < time() && !in_array($a['state'], ['approved', 'declined'], true)) {
            return ['state' => 'unknown'];
        }
        $order = wc_get_order($a['order_id']);
        return ['state' => $a['state'], 'reason' => $a['data']['reason'] ?? null,
            'retry' => $a['state'] === 'declined' && $order && $order->needs_payment() ? $order->get_checkout_payment_url() : null,
            'action' => in_array($a['state'], ['fingerprint', 'challenge'], true) ? ($a['data']['action'] ?? null) : null,
            'redirect' => $a['state'] === 'approved' && $order && $order->is_paid() ? $order->get_checkout_order_received_url() : null];
    }

    /** Called only under the order lock. Expiry never converts a submitted/unknown financial result into a retry. */
    private function expireAuthentication(array $a): array
    {
        if (!in_array($a['state'], ['fingerprint', 'challenge'], true)
            || ($a['data']['auth_expires'] ?? $a['data']['expires']) >= time()) {
            return $a;
        }
        $data = $a['data'];
        unset($data['action']);
        $data['reason'] = 'authentication_expired';
        $a = $this->store->save($a, 'declined', $data);
        $this->syncOrder($a);
        return $a;
    }
}
