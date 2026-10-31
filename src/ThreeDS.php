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

    public static function action(string $state, string $url, array $fields, ?string $expected = null): array
    {
        self::destination($url);
        $id = self::id($fields[$state === 'fingerprint' ? 'threeDSMethodData' : 'threeDSSessionData'] ?? '');
        if ($expected !== null && !hash_equals($expected, $id)) {
            throw new \DomainException('Mismatched 3DS session.');
        }
        if ($state === 'challenge' && self::id($fields['creq'] ?? '') !== $id) {
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
