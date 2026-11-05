<?php

use Brick\Math\BigDecimal;
use Illuminate\Support\Number;
use Miguilim\FilamentAutoPanel\Support\DecimalFormatter;

it('retains every available decimal digit without a fixed scale', function (mixed $state, string $expected): void {
    expect(DecimalFormatter::format($state, locale: 'en'))->toBe($expected);
})->with([
    'tiny decimal string' => ['0.0000000001', '0.0000000001'],
    'tiny float' => [1e-10, '0.0000000001'],
    'negative tiny float' => [-1e-10, '-0.0000000001'],
    'large decimal with trailing zero' => ['1234567890.1234567890', '1,234,567,890.1234567890'],
    'beyond signed 64-bit' => ['9223372036854775808', '9,223,372,036,854,775,808'],
    'beyond unsigned 64-bit' => ['184467440737095516160', '184,467,440,737,095,516,160'],
    'negative large integer' => ['-9223372036854775809', '-9,223,372,036,854,775,809'],
    'integer zero' => [0, '0'],
    'string zero' => ['0', '0'],
    'float zero' => [0.0, '0'],
    'scaled zero' => ['0.0000', '0.0000'],
    'negative zero' => ['-0.0000', '0.0000'],
    'ordinary float' => [1234.56789, '1,234.56789'],
    'full float precision' => [1.2345678901234567, '1.2345678901234567'],
    'scientific tiny string' => ['1e-10', '0.0000000001'],
    'scientific fractional zeros' => ['1.2300e-2', '0.012300'],
    'scientific positive exponent' => ['1.234567890123456789e+20', '123,456,789,012,345,678,900'],
    'scientific negative' => ['-9.99E3', '-9,990'],
    'leading decimal point' => ['.0001', '0.0001'],
    'trailing decimal point' => ['1.', '1'],
    'fraction without integer part' => ['.1', '0.1'],
    'scientific trailing decimal point' => ['1.e3', '1,000'],
    'explicit plus sign' => ['+1', '1'],
    'explicit plus sign and padding' => [' +001234.5000 ', '1,234.5000'],
]);

it('accepts scientific exponents within the expansion limit', function (string $value): void {
    expect(DecimalFormatter::isWithinExpansionLimit($value))->toBeTrue();
})->with([
    'integer' => ['+1'],
    'trailing decimal point' => ['1.'],
    'leading decimal point' => ['.1'],
    'scientific trailing decimal point' => ['1.e3'],
    'positive boundary' => ['1e10000'],
    'negative boundary' => ['1e-10000'],
    'explicit positive boundary' => ['1e+10000'],
    'zero-padded boundary' => ['1e-0000010000'],
    'whitespace around exponent' => [" \t1E+10000\n"],
    'numeric form-feed whitespace' => ["\f1e10000\f"],
    'many leading exponent zeros' => ['1e+' . str_repeat('0', 100) . '10000'],
    'zero exponent' => ['1e-' . str_repeat('0', 100)],
]);

it('returns oversized scientific input unchanged before expansion', function (string $value): void {
    expect(DecimalFormatter::isWithinExpansionLimit($value))->toBeFalse()
        ->and(DecimalFormatter::format($value, locale: 'en'))->toBe($value)
        ->and(DecimalFormatter::format($value, 2, 'de'))->toBe($value);
})->with([
    'above positive boundary' => ['1e10001'],
    'above negative boundary' => ['1e-10001'],
    'massive positive exponent' => ['1e20000000'],
    'massive negative exponent' => ['1e-20000000'],
    'beyond integer range' => ['1e999999999999999999999'],
    'negative beyond integer range' => ['1e-999999999999999999999'],
    'zero with huge positive exponent' => ['0e999999999999999999999'],
    'zero with huge negative exponent' => ['0e-999999999999999999999'],
    'signed zero and explicit positive exponent' => ['-0E+999999999999999999999'],
    'trailing decimal point' => ['1.e+10001'],
    'zero-padded excessive exponent' => ['1e+' . str_repeat('0', 100) . '10001'],
    'whitespace is preserved' => [" \t+1E-999999999999999999999\n"],
    'form-feed whitespace is preserved' => ["\f1e10001\f"],
]);

it('formats exponents at the expansion boundary', function (): void {
    expect(str_replace(',', '', DecimalFormatter::format('1e10000', locale: 'en')))
        ->toBe('1' . str_repeat('0', 10000))
        ->and(DecimalFormatter::format('1e-10000', locale: 'en'))
        ->toBe('0.' . str_repeat('0', 9999) . '1')
        ->and(DecimalFormatter::format('0e10000', locale: 'en'))->toBe('0');
});

it('does not allocate an excessive requested fixed scale', function (mixed $value, int $scale, string $expected): void {
    expect(DecimalFormatter::format($value, $scale, 'de'))->toBe($expected);
})->with([
    'above scale boundary' => ['1234.50', 10001, '1234.50'],
    'maximum integer scale' => ['1.23', PHP_INT_MAX, '1.23'],
    'original whitespace' => [' +1234.50 ', 10001, ' +1234.50 '],
    'integer zero' => [0, 10001, '0'],
    'float input' => [1.25, 10001, '1.25'],
]);

it('allows the maximum fixed scale', function (): void {
    expect(DecimalFormatter::format('1', 10000, 'en'))->toBe('1.' . str_repeat('0', 10000));
});

it('does not limit literal mantissa precision', function (): void {
    $value = '1.' . str_repeat('2', 10001);

    expect(DecimalFormatter::isWithinExpansionLimit($value))->toBeTrue()
        ->and(DecimalFormatter::format($value, locale: 'en'))->toBe($value);
});

it('rounds half up at a fixed scale without losing precision', function (mixed $state, int $scale, string $expected): void {
    expect(DecimalFormatter::format($state, $scale, 'en'))->toBe($expected);
})->with([
    'preserve large fractional digits' => ['1234567890.1234567890', 10, '1,234,567,890.1234567890'],
    'tiny nonzero' => ['0.0000000001', 10, '0.0000000001'],
    'pad scale' => ['12.3', 4, '12.3000'],
    'round down' => ['1.234', 2, '1.23'],
    'positive half up' => ['1.005', 2, '1.01'],
    'negative half up' => ['-1.005', 2, '-1.01'],
    'negative below half' => ['-1.004', 2, '-1.00'],
    'carry across grouping' => ['999.995', 2, '1,000.00'],
    'negative carry across grouping' => ['-999.995', 2, '-1,000.00'],
    'carry beyond 64-bit' => ['999999999999999999999.995', 2, '1,000,000,000,000,000,000,000.00'],
    'round to integer' => ['12.5', 0, '13'],
    'negative round to integer' => ['-12.5', 0, '-13'],
    'zero fixed scale' => [0, 2, '0.00'],
    'rounded negative zero' => ['-0.004', 2, '0.00'],
    'scientific rounding' => ['1.2345e-2', 4, '0.0123'],
    'float rounding' => [1.005, 2, '1.01'],
]);

it('uses locale-specific separators and grouping sizes', function (string $locale, string $expected): void {
    expect(DecimalFormatter::format('1234567890.1234567890', locale: $locale))->toBe($expected);
})->with([
    'English' => ['en', '1,234,567,890.1234567890'],
    'German' => ['de', '1.234.567.890,1234567890'],
    'Brazilian Portuguese' => ['pt_BR', '1.234.567.890,1234567890'],
    'Hindi' => ['hi', '1,23,45,67,890.1234567890'],
    'French Unicode grouping' => ['fr', "1\u{202F}234\u{202F}567\u{202F}890,1234567890"],
]);

it('preserves localized digits and the locale minus sign', function (): void {
    expect(DecimalFormatter::format('-1234567890.1234567890', locale: 'en@numbers=arab'))
        ->toBe("\u{061C}-١٬٢٣٤٬٥٦٧٬٨٩٠٫١٢٣٤٥٦٧٨٩٠");
});

it('supports noncontiguous localized digits', function (): void {
    expect(DecimalFormatter::format('1234.50', locale: 'zh@numbers=hanidec'))->toBe('一,二三四.五〇');
});

it('follows the Laravel Number default locale and accepts an explicit override', function (): void {
    Number::withLocale('de', function (): void {
        expect(DecimalFormatter::format('1234.50'))->toBe('1.234,50')
            ->and(DecimalFormatter::format('1234.50', locale: 'en'))->toBe('1,234.50');
    });
});

it('returns null for null or blank input', function (mixed $state): void {
    expect(DecimalFormatter::format($state))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'whitespace' => [" \t\n"],
]);

it('leaves nonnumeric text unchanged', function (string $state): void {
    expect(DecimalFormatter::format($state, 2, 'de'))->toBe($state);
})->with(['not a number', '  pending  ', '1,234.50', '12,50', '<not-numeric>', 'NaN']);

it('safely handles stringable values', function (): void {
    $text = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'pending';
        }
    };

    expect(DecimalFormatter::format($text))->toBe('pending')
        ->and(DecimalFormatter::format(BigDecimal::of('1234567890.1234567890'), locale: 'en'))
        ->toBe('1,234,567,890.1234567890');
});

it('does not coerce arrays or non-stringable objects to strings', function (): void {
    expect(DecimalFormatter::format(['value' => 123]))->toBeNull()
        ->and(DecimalFormatter::format(new stdClass()))->toBeNull();
});

it('leaves nonfinite floats as text', function (): void {
    expect(DecimalFormatter::format(INF))->toBe('INF')
        ->and(DecimalFormatter::format(-INF))->toBe('-INF')
        ->and(DecimalFormatter::format(NAN))->toBe('NAN');
});
