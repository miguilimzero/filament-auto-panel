<?php

namespace Miguilim\FilamentAutoPanel\Filament\StateCasts;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;


class JsonStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return $state;
    }

    public function set(mixed $state): ?string
    {
        return $state === null ? null : json_encode(
            $state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }
}
