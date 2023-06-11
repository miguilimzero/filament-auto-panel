<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Resources\Pages\CreateRecord;

class FilamentAutoResourceCreate extends CreateRecord
{
    public static function setResource(string $resource): void
    {
        self::$resource = $resource;
    }
}
