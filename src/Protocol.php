<?php
namespace Y2KWebs\Lafise;

/** Pure protocol mapping; fixtures and private credentials never belong here. */
final class Protocol
{
    public array $definition;

    public function __construct()
    {
        $json = ltrim(file_get_contents(dirname(__DIR__) . '/config/protocol.json'), "\xEF\xBB\xBF");
        $this->definition = json_decode($json, true, 32, JSON_THROW_ON_ERROR)['operations'];
        $this->assertValid();
    }

    /** Signature overrides may authenticate 3DS continuations, never financial outcomes. */
    public function assertValid(): void
    {
        foreach ($this->definition as $name => $operation) {
            $schema = $operation['response'] ?? [];
            if (!array_key_exists('signature_by_code', $schema)) { continue; }
            $variants = $schema['signature_by_code'];
            if (!is_array($variants) || ($variants !== [] && ($name !== 'sale' || ($operation['authorization'] ?? '') !== 'bearer'))) {
                throw new \DomainException('Invalid signature variant contract.');
            }
            foreach ($variants as $code => $rule) {
                $states = $schema['states'] ?? [];
                $fields = is_array($rule) ? ($rule['fields'] ?? null) : null;
                if (!is_string($code) || !in_array($code, array_merge($states['fingerprint'] ?? [], $states['challenge'] ?? []), true)
                    || in_array($code, array_merge($states['approved'] ?? [], $states['declined'] ?? []), true)
                    || !is_array($rule) || ($rule['algorithm'] ?? '') !== 'sha256'
                    || !is_array($fields) || !array_is_list($fields)
                    || count(array_filter($fields, 'is_string')) !== count($fields)
                    || array_diff(['merchant_pay_id', 'bank_reference', 'code', 'amount', 'merchant', 'currency'], $fields) !== []) {
                    throw new \DomainException('Invalid signature variant contract.');
                }
            }
        }
    }

    public static function path(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $part) {
            if (!is_array($data) || !array_key_exists($part, $data)) {
                return null;
            }
            $data = $data[$part];
        }
        return $data;
    }

    public static function sign(array $fields, array $context): string
    {
        $text = '';
        foreach ($fields as $field) {
            if (!array_key_exists($field, $context) || !is_scalar($context[$field])) {
                throw new \DomainException('Missing signature field.');
            }
            $text .= (string) $context[$field];
        }
        return hash('sha256', $text);
    }

    public static function expand(mixed $template, array $context): mixed
    {
        if (is_array($template)) {
            return array_map(static fn ($v) => self::expand($v, $context), $template);
        }
        if (is_string($template) && str_starts_with($template, '@')) {
            return $context[substr($template, 1)] ?? throw new \DomainException('Missing request field.');
        }
        return $template;
    }

    public function request(string $operation, array $context): array
    {
        $this->assertValid();
        $definition = $this->definition[$operation] ?? throw new \DomainException('Unsupported operation.');
        foreach ($definition['signatures'] ?? [] as $name => $rule) {
            $context[$name] = self::sign($rule['fields'], $context);
        }
        $body = self::expand($definition['request'], $context);
        foreach ($definition['empty_fields_as_null'] ?? [] as $field) {
            if (($body[$field] ?? null) === '') {
                $body[$field] = null;
            }
        }
        return $body;
    }

    public function validate(string $operation, array $payload, array $context, array $prior = [], bool $authenticatedTransport = false): array
    {
        $this->assertValid();
        if ($operation === 'inquire') {
            if (!$authenticatedTransport) { throw new \DomainException('Unauthenticated bank query.'); }
            $extra = $payload['ExtraFile'] ?? [];
            if (($payload['Status'] ?? '') !== 'OK' || ($payload['ResponseCode'] ?? '') !== '00'
                || empty($prior['receipt']['bank_reference']) || empty($prior['ref3'])
                || ($extra['OrderId'] ?? null) !== $prior['ref3']
                || ($extra['Terminal_Id'] ?? null) !== $context['terminal_id']
                || ($extra['Currency'] ?? null) !== $context['currency']
                || !is_string($extra['Amount'] ?? null) || Money::minor($extra['Amount']) !== (int) $context['amount']) {
                throw new \DomainException('Unconfirmed query.');
            }
            return ['state' => 'approved', 'receipt' => $prior['receipt'], 'ref3' => $prior['ref3']];
        }
        $schema = $this->definition[$operation]['response'];
        if ($operation === 'process' && ($payload['ResponseCode'] ?? '') === '3DS-PRC-002') {
            if (!$authenticatedTransport || empty($prior['transaction_id']) || empty($prior['receipt'])) {
                throw new \DomainException('Missing prior receipt.');
            }
            $action = ThreeDS::action('challenge', $payload['Action'] ?? '',
                ['creq' => $payload['Creq'] ?? '', 'threeDSSessionData' => $payload['ThreeDSSessionData'] ?? ''], $prior['transaction_id']);
            return ['state' => 'challenge', 'receipt' => $prior['receipt'], 'action' => $action,
                'transaction_id' => $action['transaction_id'], 'ref3' => $prior['ref3'] ?? ''];
        }
        $data = [];
        foreach ($schema['paths'] as $name => $path) {
            $v = self::path($payload, $path);
            if ($v !== null && (!is_scalar($v) || strlen((string) $v) > 255)) {
                throw new \DomainException('Invalid bank field.');
            }
            $data[$name] = $v === null ? '' : (string) $v;
        }
        foreach (['code', 'amount', 'currency', 'merchant', 'terminal', 'signature'] as $key) {
            if ($data[$key] === '') {
                throw new \DomainException('Incomplete bank result.');
            }
        }
        $minor = $schema['amount_unit'] === 'major' ? Money::minor($data['amount'])
            : (preg_match('/^[0-9]{1,12}$/D', $data['amount']) ? (int) $data['amount'] : -1);
        if ($minor !== (int) $context['amount'] || $data['merchant'] !== $context['merchant_id']
            || $data['terminal'] !== $context['terminal_id'] || $data['currency'] !== $context['currency']) {
            throw new \DomainException('Mismatched financial result.');
        }
        $rule = $schema['signature_by_code'][$data['code']] ?? $schema['signature'];
        $signature = self::sign($rule['fields'], array_merge($context, $data));
        if (!preg_match('/^[a-f0-9]{64}$/iD', $data['signature']) || !hash_equals($signature, strtolower($data['signature']))) {
            throw new \DomainException('Invalid financial signature.');
        }
        $state = 'unknown';
        foreach ($schema['states'] as $candidate => $codes) {
            if (in_array($data['code'], $codes, true)) {
                $state = $candidate;
            }
        }
        // Direct financial Sale outcomes remain unclassified pending additional evidence.
        if ($state === 'approved' && (!$data['bank_reference'] || !$data['authorization'])) {
            throw new \DomainException('Incomplete approval.');
        }
        if ($state === 'declined' && $data['authorization'] !== '') {
            throw new \DomainException('Conflicting decline authorization.');
        }
        unset($data['signature']);
        $result = ['state' => $state, 'receipt' => $data, 'ref3' => trim($data['ref3'])];
        if (in_array($state, ['fingerprint', 'challenge'], true)) {
            if (!$authenticatedTransport) { throw new \DomainException('Unauthenticated 3DS destination.'); }
            $map = $schema['three_ds'];
            $fields = $state === 'fingerprint'
                ? ['threeDSMethodData' => self::path($payload, $map['method_data'])]
                : ['creq' => self::path($payload, $map['creq']), 'threeDSSessionData' => self::path($payload, $map['session_data'])];
            try {
                $action = ThreeDS::action($state, self::path($payload, $map[$state === 'fingerprint' ? 'method_url' : 'action']) ?? '',
                    $fields, $prior['transaction_id'] ?? null);
            } catch (\Throwable $error) {
                if ($operation === 'sale' && $data['authorization'] === '') {
                    return ['state' => 'declined', 'reason' => 'authentication_setup_failed'];
                }
                throw $error;
            }
            $result['action'] = $action;
            $result['transaction_id'] = $action['transaction_id'];
        }
        return $result;
    }
}
