<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class FilamentAutoResourceList extends ListRecords
{
    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
