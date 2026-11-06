<?php
namespace Y2KWebs\Lafise;

final class ThreeDS
{
    public static function destination(string $url): string
    {
        $p = wp_parse_url($url);
        $host = strtolower($p['host'] ?? '');
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !$p
            || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])
            || (isset($p['port']) && $p['port'] !== 443) || filter_var($host, FILTER_VALIDATE_IP)
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
            || preg_match('/\.(?:localhost|local|internal|lan|home|test|invalid|example)$/D', $host)) {
            throw new \DomainException('Unsafe issuer destination.');
        }
        return $url;
    }

    public static function decode(string $value): string
    {
        if (!$value || strlen($value) > 16384 || !preg_match('/^[A-Za-z0-9_+\/=-]+$/D', $value)) {
            throw new \DomainException('Invalid 3DS encoding.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \DomainException('Invalid 3DS encoding.');
        }
        return $decoded;
    }

    public static function id(string $encoded): string
    {
        $decoded = self::decode($encoded);
        $json = json_decode($decoded, true);
        $id = is_array($json) ? ($json['threeDSServerTransID'] ?? '') : $decoded;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $id)) {
            throw new \DomainException('Invalid 3DS identifier.');
        }
        return $id;
    }

    /** Fixed internal codes only; exception text and bank payloads are never retained. */
    public static function failure(\Throwable $error): ?string
    {
        if (!$error instanceof \DomainException) { return null; }
        return match ($error->getMessage()) {
            'Unsafe issuer destination.' => 'invalid_destination',
            'Ambiguous 3DS field.' => 'ambiguous_fields',
            'Invalid 3DS method_data.' => 'invalid_method_data',
            'Invalid 3DS creq.' => 'invalid_creq',
            'Invalid 3DS session_data.' => 'invalid_session_data',
            'Invalid 3DS identifier.' => 'invalid_transaction_id',
            'Mismatched 3DS session.', 'Mismatched challenge.' => 'challenge_identifier_mismatch',
            'Missing prior receipt.', 'Invalid 3DS continuation.' => 'invalid_continuation',
            default => null,
        };
    }

    private static function actionId(mixed $value, string $field): string
    {
        $valid = is_string($value);
        if ($valid) {
            try { self::decode($value); }
            catch (\DomainException) { $valid = false; }
        }
        if (!$valid) {
            throw match ($field) {
                'method_data' => new \DomainException('Invalid 3DS method_data.'),
                'creq' => new \DomainException('Invalid 3DS creq.'),
                'session_data' => new \DomainException('Invalid 3DS session_data.'),
                default => new \DomainException('Invalid 3DS continuation.'),
            };
        }
        return self::id($value);
    }

    public static function action(string $state, mixed $url, array $fields, ?string $expected = null): array
    {
        if (!is_string($url)) { throw new \DomainException('Unsafe issuer destination.'); }
        self::destination($url);
        $id = self::actionId($fields[$state === 'fingerprint' ? 'threeDSMethodData' : 'threeDSSessionData'] ?? null,
            $state === 'fingerprint' ? 'method_data' : 'session_data');
        if ($expected !== null && !hash_equals($expected, $id)) {
            throw new \DomainException('Mismatched 3DS session.');
        }
        if ($state === 'challenge' && self::actionId($fields['creq'] ?? null, 'creq') !== $id) {
            throw new \DomainException('Mismatched challenge.');
        }
        return ['action' => $url, 'fields' => $fields, 'transaction_id' => $id];
    }

    public static function callback(array $input, string $state, string $expected): array
    {
        if ($state === 'fingerprint') {
            $data = $input['threeDSMethodData'] ?? '';
            if (!is_string($data) || self::id($data) !== $expected) {
                throw new \DomainException('Mismatched fingerprint.');
            }
            return ['three_ds_data' => $data, 'cres' => '', 'session_data' => ''];
        }
        $session = $input['threeDSSessionData'] ?? '';
        $cres = $input['cres'] ?? '';
        if ($state !== 'challenge' || !is_string($session) || !is_string($cres)
            || self::id($session) !== $expected || self::id($cres) !== $expected) {
            throw new \DomainException('Mismatched challenge return.');
        }
        return ['three_ds_data' => '', 'cres' => $cres, 'session_data' => $session];
    }
}
