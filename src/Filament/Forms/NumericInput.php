<?php

namespace Miguilim\FilamentAutoPanel\Filament\Forms;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Schemas\Components\StateCasts\NumberStateCast;
use Miguilim\FilamentAutoPanel\Filament\StateCasts\NumericStringStateCast;
use Miguilim\FilamentAutoPanel\Support\DecimalFormatter;

class NumericInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->numeric()->type('text')
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! DecimalFormatter::isWithinExpansionLimit($value)) {
                    $fail(__('validation.numeric', ['attribute' => $attribute]));
                }
            }, static fn (NumericInput $component): bool => $component->isNumeric());
    }

    public function getDefaultStateCasts(): array
    {
        return array_map(
            fn (StateCast $cast): StateCast => $cast instanceof NumberStateCast
                ? new NumericStringStateCast($this->getStripCharacters())
                : $cast,
            parent::getDefaultStateCasts(),
        );
    }
}
