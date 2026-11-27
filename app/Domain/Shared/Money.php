<?php

namespace App\Domain\Shared;

use InvalidArgumentException;

/**
 * Exact peso amounts as integer centavos, without floats. decimal(18,2) values from PostgreSQL arrive
 * as strings; convert them here before comparing or adding in PHP.
 */
final class Money
{
    public static function toCents(string|int|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_int($amount)) {
            return $amount * 100;
        }
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2})\d*)?$/', trim($amount), $m)) {
            throw new InvalidArgumentException("Not a decimal amount: {$amount}");
        }
        if (isset($m[3]) && preg_match('/^-?\d+\.\d{2}(\d*[1-9])/', trim($amount))) {
            throw new InvalidArgumentException("Amounts carry at most two decimal places: {$amount}");
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(string|int|null $amount): string
    {
        $cents = self::toCents($amount);

        return ($cents < 0 ? '-' : '').'₱'.number_format(intdiv(abs($cents), 100)).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Display a unit cost (up to four decimals, at least two), e.g. 33.3333 -> ₱33.3333, 250 -> ₱250.00. */
    public static function formatUnitCost(string|int|null $cost): string
    {
        $cost = (string) ($cost ?? '0');
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $cost, $m)) {
            return $cost;
        }
        $decimals = rtrim(substr(($m[3] ?? '').'0000', 0, 4), '0');
        $decimals = str_pad($decimals, 2, '0');

        return $m[1].'₱'.number_format((int) $m[2]).'.'.$decimals;
    }
}
