<?php
namespace Y2KWebs\Lafise;

final class Bank
{
    private array $diagnostic = [];
    public function __construct(private Config $config, private Protocol $protocol = new Protocol()) {}

    private function request(string $operation, array $context, ?array $auth = null, ?bool &$submitted = null): array
    {
        $this->diagnostic = ['operation' => $operation, 'stage' => 'prepare'];
        $def = $this->protocol->definition[$operation];
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($auth !== null) {
            $headers['Authorization'] = 'Bearer ' . $auth['token'];
        }
        $body = wp_json_encode($this->protocol->request($operation, $context));
        if (!is_string($body)) { throw new \RuntimeException('Invalid request encoding.'); }
        $this->diagnostic['stage'] = 'transport';
        $submitted = true;
        $response = wp_safe_remote_post($def['url'], ['timeout' => 30, 'redirection' => 0,
            'sslverify' => true, 'limit_response_size' => 131072, 'headers' => $headers,
            'body' => $body]);
        if (is_wp_error($response)) { throw new \RuntimeException('Bank transport failed.'); }
        $httpStatus = (int) wp_remote_retrieve_response_code($response);
        $this->diagnostic['http_status'] = $httpStatus;
        if ($httpStatus !== 200) {
            throw new \RuntimeException('Bank response unavailable.');
        }
        $this->diagnostic['stage'] = 'decode';
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if ($operation === 'sale' && is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }
        if (is_array($decoded)) {
            $code = $decoded['ResponseCode'] ?? null;
            if (is_string($code) && preg_match('/^(?:[0-9]{2,3}|3DS-[A-Z]{3}-[0-9]{3})$/D', $code)) {
                $this->diagnostic['response_code'] = $code;
            }
            return $decoded;
        }
        if ($operation === 'process') {
            return self::continuation(is_string($decoded) ? $decoded : $body);
        }
        throw new \RuntimeException('Invalid bank response.');
    }

    public static function continuation(string $content): array
    {
        if (strlen($content) > 131072) {
            throw new \DomainException('Oversized continuation.');
        }
        $json = json_decode($content, true);
        if (is_array($json)) {
            return $json;
        }
        $doc = new \DOMDocument();
        if (!$doc->loadHTML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \DomainException('Invalid continuation.');
        }
        $found = [];
        foreach ($doc->getElementsByTagName('form') as $form) {
            if (strtoupper($form->getAttribute('method')) !== 'POST') {
                continue;
            }
            $fields = [];
            foreach ($form->getElementsByTagName('input') as $input) {
                $name = strtolower($input->getAttribute('name'));
                if (in_array($name, ['creq', 'threedssessiondata'], true)) {
                    if (isset($fields[$name])) {
                        throw new \DomainException('Ambiguous continuation.');
                    }
                    $fields[$name] = $input->getAttribute('value');
                }
            }
            if (isset($fields['creq'], $fields['threedssessiondata'])) {
                $found[] = ['ResponseCode' => '3DS-PRC-002', 'Action' => $form->getAttribute('action'),
                    'Creq' => $fields['creq'], 'ThreeDSSessionData' => $fields['threedssessiondata']];
            }
        }
        if (count($found) !== 1) {
            throw new \DomainException('Ambiguous continuation.');
        }
        return $found[0];
    }

    public function authenticate(): array
    {
        $body = $this->request('authenticate', $this->config->context());
        $token = $body['access_token'] ?? null;
        $encoded = $body['serverTransportKey'] ?? null;
        if (!is_string($token) || !$token || strlen($token) > 16384 || !is_string($encoded) || strlen($encoded) > 16384) {
            throw new \DomainException('Invalid authentication.');
        }
        $pem = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (!$pem || !str_starts_with($pem, '-----BEGIN PUBLIC KEY-----')) {
            throw new \DomainException('Invalid transport key.');
        }
        $key = openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (!$details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048 || $details['bits'] > 4096) {
            throw new \DomainException('Invalid transport key.');
        }
        $encode = static fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        return ['token' => $token, 'jwk' => ['kty' => 'RSA', 'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e'])]];
    }

    public function execute(string $operation, array $context, array $prior = [], ?array $auth = null): array
    {
        $this->diagnostic = ['operation' => $operation, 'stage' => 'authentication'];
        $submitted = false;
        try {
            $auth ??= $this->authenticate();
            $context = array_merge($this->config->context(), $context);
            $payload = $this->request($operation, $context, $auth, $submitted);
            $this->diagnostic['stage'] = 'validation';
            $result = $this->protocol->validate($operation, $payload, $context, $prior, true);
            if ($result['state'] === 'unknown') {
                return $result + ['reason' => 'unrecognized_bank_result', 'diagnostic' => $this->diagnostic + ['failure' => 'unrecognized_result']];
            }
            return $result;
        } catch (\Throwable $error) {
            // Never log exception text, HTTP bodies, tokens or encrypted card data.
            $this->diagnostic['failure'] = match ($error->getMessage()) {
                'Missing request field.', 'Missing signature field.', 'Invalid request encoding.' => 'request_preparation_failed',
                'Bank transport failed.' => 'transport_failed',
                'Bank response unavailable.' => 'http_error',
                'Invalid bank response.' => 'response_format_invalid',
                'Incomplete bank result.' => 'response_fields_missing',
                'Mismatched financial result.' => 'financial_result_mismatch',
                'Invalid financial signature.' => 'signature_mismatch',
                default => 'response_validation_failed',
            };
            if ($operation === 'sale' && !$submitted) {
                return ['state' => 'declined', 'reason' => 'payment_not_sent', 'diagnostic' => $this->diagnostic];
            }
            return ['state' => 'unknown', 'reason' => 'bank_result_unavailable', 'diagnostic' => $this->diagnostic];
        }
    }
}
