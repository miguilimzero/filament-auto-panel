<?php

namespace Miguilim\FilamentAutoPanel\Filament\StateCasts;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

class NumericStringStateCast implements StateCast
{
    public function __construct(protected array $stripCharacters = [])
    {
    }

    public function get(mixed $state): mixed
    {
        if (is_string($state)) {
            $state = str_replace($this->stripCharacters, '', $state);

            return trim($state) === '' ? null : $state;
        }

        if (is_int($state)) {
            return (string) $state;
        }

        // A PHP string cast can truncate floats using the configured `precision`.
        return is_float($state) && is_finite($state)
            ? json_encode($state, JSON_THROW_ON_ERROR)
            : $state;
    }

    public function set(mixed $state): mixed
    {
        return $this->get($state);
    }
}
