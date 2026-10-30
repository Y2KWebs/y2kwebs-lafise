<?php
namespace Y2KWebs\Lafise;

final class Money
{
    public static function minor(string $value): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new \DomainException('Invalid amount.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    public static function major(int $minor): string
    {
        if ($minor < 0) {
            throw new \DomainException('Invalid amount.');
        }
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
