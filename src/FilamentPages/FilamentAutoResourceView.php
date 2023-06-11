<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;

class FilamentAutoResourceView extends ViewRecord
{
    public static function setResource(string $resource): void
    {
        self::$resource = $resource;
    }

    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
