<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic for monetary values stored with two decimals.
 * Values are converted to integer cents and returned as decimal strings.
 */
final class Money
{
    public static function normalize(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $text)) {
            throw new InvalidArgumentException('Nilai uang tidak valid.');
        }

        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '+-');
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) $cents += 1;

        return self::fromCents($negative ? -$cents : $cents);
    }

    public static function toCents(mixed $value): int
    {
        $normalized = self::normalize($value);
        $negative = str_starts_with($normalized, '-');
        $text = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $text);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $result = intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$result : $result;
    }

    public static function add(mixed ...$values): string
    {
        return self::fromCents(array_sum(array_map([self::class, 'toCents'], $values)));
    }

    public static function subtract(mixed $left, mixed $right): string
    {
        return self::fromCents(self::toCents($left) - self::toCents($right));
    }

    public static function multiply(mixed $value, int $quantity): string
    {
        return self::fromCents(self::toCents($value) * $quantity);
    }

    public static function percent(mixed $value, mixed $rate): string
    {
        $amountCents = self::toCents($value);
        $rate = self::normalize($rate);
        $negative = str_starts_with($rate, '-');
        $rate = ltrim($rate, '-');
        [$whole, $fraction] = explode('.', $rate);
        $rateBasisPoints = ((int) $whole * 100) + (int) $fraction;
        if ($negative) $rateBasisPoints *= -1;

        $numerator = $amountCents * $rateBasisPoints;
        $denominator = 10000; // cents * (rate * 100) / 10000 = cents
        $rounded = $numerator >= 0
            ? intdiv($numerator + intdiv($denominator, 2), $denominator)
            : -intdiv(abs($numerator) + intdiv($denominator, 2), $denominator);

        return self::fromCents($rounded);
    }

    public static function min(mixed $left, mixed $right): string
    {
        return self::toCents($left) <= self::toCents($right) ? self::normalize($left) : self::normalize($right);
    }

    public static function max(mixed $left, mixed $right): string
    {
        return self::toCents($left) >= self::toCents($right) ? self::normalize($left) : self::normalize($right);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return self::toCents($left) <=> self::toCents($right);
    }

    public static function jsonValue(mixed $value): int|string
    {
        $cents = self::toCents($value);
        return $cents % 100 === 0 ? intdiv($cents, 100) : self::fromCents($cents);
    }
}
