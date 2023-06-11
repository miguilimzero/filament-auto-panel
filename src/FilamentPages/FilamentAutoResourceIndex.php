<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class FilamentAutoResourceIndex extends ManageRecords
{
    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public static function setResource(string $resource): void
    {
        self::$resource = $resource;
    }
}
