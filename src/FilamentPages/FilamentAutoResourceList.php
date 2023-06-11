<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class FilamentAutoResourceList extends ListRecords
{
    public static function setResource(string $resource): void
    {
        self::$resource = $resource;
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
