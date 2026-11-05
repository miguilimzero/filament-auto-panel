<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Number;
use NumberFormatter;
use Stringable;

class DecimalFormatter
{
    // Bound scientific-notation and fixed-scale expansion, not literal precision.
    protected const MAX_EXPANSION = 10_000;

    public static function isWithinExpansionLimit(string $value): bool
    {
        if (! preg_match('/[eE]([+-]?[0-9]+)\s*\z/', $value, $matches)) {
            return true;
        }

        $exponent = ltrim($matches[1], '+-0');

        return strlen($exponent) <= strlen((string) static::MAX_EXPANSION)
            && (int) $exponent <= static::MAX_EXPANSION;
    }

    public static function format(mixed $state, ?int $decimalPlaces = null, ?string $locale = null): ?string
    {
        if ($state === null || (! is_scalar($state) && ! $state instanceof Stringable)) {
            return null;
        }

        if (is_float($state) && ! is_finite($state)) {
            return is_nan($state) ? 'NAN' : ($state < 0 ? '-INF' : 'INF');
        }

        $value = is_float($state) ? json_encode($state, JSON_THROW_ON_ERROR) : (string) $state;

        if (trim($value) === '') {
            return null;
        }

        if (! is_numeric($value)
            || ! static::isWithinExpansionLimit($value)
            || ($decimalPlaces !== null && $decimalPlaces > static::MAX_EXPANSION)) {
            return $value;
        }

        $number = BigDecimal::of(trim($value));

        if ($decimalPlaces !== null) {
            $roundingMode = defined(RoundingMode::class . '::HalfUp') ? RoundingMode::HalfUp : constant(RoundingMode::class . '::HALF_UP');
            $number = $number->toScale($decimalPlaces, $roundingMode);
        } elseif (is_float($state)) {
            $number = $number->strippedOfTrailingZeros();
        }

        $formatter = new NumberFormatter($locale ?? Number::defaultLocale(), NumberFormatter::DECIMAL);
        $value = (string) $number;
        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, null);
        $groupSize = $formatter->getAttribute(NumberFormatter::GROUPING_SIZE);

        if ($formatter->getAttribute(NumberFormatter::GROUPING_USED) && $groupSize > 0) {
            $secondaryGroupSize = $formatter->getAttribute(NumberFormatter::SECONDARY_GROUPING_SIZE) ?: $groupSize;
            $groups = [];

            do {
                $groups[] = substr($integer, -$groupSize);
                $integer = substr($integer, 0, -$groupSize);
                $groupSize = $secondaryGroupSize;
            } while ($integer !== '');

            $integer = implode((string) $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL), array_reverse($groups));
        }

        $result = ($negative ? $formatter->getSymbol(NumberFormatter::MINUS_SIGN_SYMBOL) : '') . $integer;

        if ($fraction !== null) {
            $result .= $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL) . $fraction;
        }

        if ($formatter->getSymbol(NumberFormatter::ZERO_DIGIT_SYMBOL) !== '0') {
            $digits = [];

            // ICU only formats individual digits, never the arbitrary-precision value.
            for ($digit = 0; $digit <= 9; $digit++) {
                $digits[(string) $digit] = $formatter->format($digit);
            }

            $result = strtr($result, $digits);
        }

        return $result;
    }
}
