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

    /** Only 3DS leaf names are case-insensitive; reject aliases even when their values match. */
    private static function threeDsValue(array $payload, string $path): mixed
    {
        if ($path === '') { return null; }
        $parts = explode('.', $path);
        $name = array_pop($parts);
        $parent = $parts === [] ? $payload : self::path($payload, implode('.', $parts));
        if (!is_array($parent)) { return null; }
        $found = false;
        $value = null;
        foreach ($parent as $key => $candidate) {
            if (!is_string($key) || strcasecmp($key, $name) !== 0) { continue; }
            if ($found) { throw new \DomainException('Ambiguous 3DS field.'); }
            $found = true;
            $value = $candidate;
        }
        return $value;
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
        if ($operation === 'process' && ($payload['ResponseCode'] ?? '') === '3DS-RES-003') {
            return $this->authenticationFailure($payload, $context, $prior, $authenticatedTransport);
        }
        if ($operation === 'process' && ($payload['ResponseCode'] ?? '') === '3DS-PRC-002') {
            if (!$authenticatedTransport || empty($prior['transaction_id']) || empty($prior['receipt'])) {
                throw new \DomainException('Missing prior receipt.');
            }
            $map = $schema['challenge'];
            $action = ThreeDS::action('challenge', self::threeDsValue($payload, $map['action']),
                ['creq' => self::threeDsValue($payload, $map['creq']), 'threeDSSessionData' => self::threeDsValue($payload, $map['session_data'])], $prior['transaction_id']);
            return ['state' => 'challenge', 'receipt' => $prior['receipt'], 'action' => $action,
                'transaction_id' => $action['transaction_id'], 'ref3' => $prior['ref3'] ?? '',
                'three_ds_context' => self::stageContext('challenge', $action['transaction_id'], $context, $prior['receipt'], 'process')];
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
            try {
                $fields = $state === 'fingerprint'
                    ? ['threeDSMethodData' => self::threeDsValue($payload, $map['method_data'])]
                    : ['creq' => self::threeDsValue($payload, $map['creq']), 'threeDSSessionData' => self::threeDsValue($payload, $map['session_data'])];
                $action = ThreeDS::action($state, self::threeDsValue($payload, $map[$state === 'fingerprint' ? 'method_url' : 'action']),
                    $fields, $prior['transaction_id'] ?? null);
            } catch (\Throwable $error) {
                if ($operation === 'sale' && $data['authorization'] === '') {
                    return ['state' => 'declined', 'reason' => 'authentication_setup_failed',
                        'diagnostic' => ['three_ds_failure' => ThreeDS::failure($error) ?? 'invalid_continuation']];
                }
                throw $error;
            }
            $result['action'] = $action;
            $result['transaction_id'] = $action['transaction_id'];
            $result['three_ds_context'] = self::stageContext($state, $action['transaction_id'], $context, $data, 'sale');
        }
        return $result;
    }

    /** Bind a validated stage to its local attempt and original signed receipt, without interactive fields. */
    private static function stageContext(string $stage, string $id, array $context, array $receipt, string $source): array
    {
        return ['stage' => $stage, 'transaction_id' => $id, 'reference' => $context['reference'] ?? '',
            'code' => $stage === 'fingerprint' ? '3DS-PRC-001' : '3DS-PRC-002', 'source_operation' => $source,
            'receipt_hash' => hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR)), 'submitted' => false];
    }

    /** An authenticated, receipt-free authentication failure cannot classify any financial result. */
    private function authenticationFailure(array $payload, array $context, array $prior, bool $authenticated): array
    {
        $operation = $this->definition['process'];
        $step = $prior['three_ds_context'] ?? [];
        $receipt = $prior['receipt'] ?? [];
        $stage = $step['stage'] ?? '';
        $id = $prior['transaction_id'] ?? '';
        $source = $step['source_operation'] ?? '';
        if (!$authenticated || ($operation['method'] ?? '') !== 'POST' || ($operation['authorization'] ?? '') !== 'bearer'
            || ($operation['response']['kind'] ?? '') !== 'process_3ds'
            || ($context['order_unpaid'] ?? false) !== true || !in_array($context['attempt_state'] ?? '', ['processing', 'unknown'], true)
            || !in_array($stage, ['fingerprint', 'challenge'], true) || ($step['submitted'] ?? false) !== true
            || !is_string($id) || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $id)
            || ($step['transaction_id'] ?? '') !== $id || empty($context['reference']) || ($step['reference'] ?? '') !== $context['reference']
            || ($step['code'] ?? '') !== ($stage === 'fingerprint' ? '3DS-PRC-001' : '3DS-PRC-002')
            || !in_array($source, ['sale', 'process'], true) || ($stage === 'fingerprint' && $source !== 'sale')
            || !in_array($receipt['code'] ?? '', $source === 'sale' ? [$step['code'] ?? ''] : ['3DS-PRC-001', '3DS-PRC-002'], true)
            || ($step['receipt_hash'] ?? '') !== hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR))
            || ($receipt['merchant'] ?? null) !== $context['merchant_id'] || ($receipt['terminal'] ?? null) !== $context['terminal_id']
            || ($receipt['currency'] ?? null) !== $context['currency'] || !is_string($receipt['amount'] ?? null)
            || !preg_match('/^[0-9]{1,12}$/D', $receipt['amount']) || (int) $receipt['amount'] !== (int) $context['amount']
            || self::financialEvidence($prior)) {
            throw new \DomainException('Invalid authentication rejection context.');
        }
        // Inspect aliases only to reject contradictory evidence; accepted financial field names remain exact.
        $scan = static function (array $fields, bool $financial = false) use (&$scan): void {
            foreach ($fields as $key => $value) {
                $name = strtolower((string) $key);
                if ($name === 'responsecode' && $value !== '3DS-RES-003') {
                    throw new \DomainException('Conflicting authentication rejection code.');
                }
                $container = in_array($name, ['paymentdata', 'paymentinformation', 'apisecurity'], true);
                $sensitive = $financial || $container || in_array($name, ['authorizationcode', 'authorization', 'message_signature', 'signature', 'code',
                    'amount', 'currency_code', 'currency', 'merchant_id', 'terminal_id', 'ref1', 'ref_1', 'ref3', 'ref_3', 'bank_reference',
                    'authorization_code', 'provider_reference', 'providerreference', 'tax', 'taxes'], true);
                if (is_array($value)) {
                    if ($sensitive && $value === []) { throw new \DomainException('Authentication rejection contains financial data.'); }
                    $scan($value, $sensitive);
                } elseif ($sensitive && $value !== null && $value !== '') {
                    throw new \DomainException('Authentication rejection contains financial data.');
                }
            }
        };
        $scan($payload);
        return ['state' => 'declined', 'reason' => 'authentication_failed',
            'diagnostic' => ['failure' => 'authentication_failed']];
    }

    public static function financialEvidence(array $data): bool
    {
        foreach (['authorization', 'authorization_code', 'provider_reference', 'bank_reference'] as $name) {
            if (($data[$name] ?? '') !== '' && ($data[$name] ?? null) !== null) { return true; }
        }
        foreach (['authorization', 'bank_reference'] as $name) {
            if (($data['receipt'][$name] ?? '') !== '' && ($data['receipt'][$name] ?? null) !== null) { return true; }
        }
        return false;
    }
}
