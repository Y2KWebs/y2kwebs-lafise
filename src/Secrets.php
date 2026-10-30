<?php
namespace Y2KWebs\Lafise;

final class Secrets
{
    public static function seal(array $value): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt(json_encode($value, JSON_THROW_ON_ERROR), 'aes-256-gcm',
            hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag, 'y2k-lafise-v1');
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption unavailable.');
        }
        return base64_encode($iv . $tag . $encrypted);
    }

    public static function open(string $value): array
    {
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Invalid protected data.');
        }
        $json = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true),
            OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'y2k-lafise-v1');
        if ($json === false) {
            throw new \RuntimeException('Protected data unavailable.');
        }
        return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }
}
