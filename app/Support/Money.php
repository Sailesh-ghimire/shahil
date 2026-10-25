<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Money is stored as integer minor units (paisa, cents) in the workspace currency.
 */
class Money
{
    public static function toMinor(float|int|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) str_replace(',', '', (string) $amount)) * 100);
    }

    public static function fromMinor(?int $minor): float
    {
        return ($minor ?? 0) / 100;
    }

    /**
     * "Rs 1,50,000" for NPR/INR (Indian grouping), "USD 1,500.00" style otherwise.
     */
    public static function format(?int $minor, ?string $currency = 'NPR', bool $withDecimals = false): string
    {
        $amount = static::fromMinor($minor);
        $currency ??= 'NPR';

        if (in_array($currency, ['NPR', 'INR'], true)) {
            return 'Rs '.static::indianGrouping($amount, $withDecimals);
        }

        return $currency.' '.Number::format($amount, $withDecimals ? 2 : 0);
    }

    /**
     * Compact form for board column totals: "Rs 12.5 lakh", "Rs 1.2 crore".
     */
    public static function short(?int $minor, ?string $currency = 'NPR'): string
    {
        $amount = static::fromMinor($minor);

        if (in_array($currency ?? 'NPR', ['NPR', 'INR'], true)) {
            return match (true) {
                $amount >= 10_000_000 => 'Rs '.round($amount / 10_000_000, 1).' crore',
                $amount >= 100_000 => 'Rs '.round($amount / 100_000, 1).' lakh',
                default => static::format($minor, $currency),
            };
        }

        return ($currency ?? '').' '.Number::abbreviate($amount, maxPrecision: 1);
    }

    protected static function indianGrouping(float $amount, bool $withDecimals): string
    {
        $negative = $amount < 0;
        $fixed = number_format(abs($amount), $withDecimals ? 2 : 0, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $fixed), 2, null);

        $lastThree = substr($whole, -3);
        $rest = substr($whole, 0, -3);

        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest.','.$lastThree;
        }

        return ($negative ? '-' : '').$whole.($fraction !== null ? '.'.$fraction : '');
    }
}
