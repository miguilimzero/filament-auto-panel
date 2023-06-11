<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;

class FilamentAutoResourceView extends ViewRecord
{
    protected function getActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
