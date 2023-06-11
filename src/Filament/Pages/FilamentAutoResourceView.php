<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

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
