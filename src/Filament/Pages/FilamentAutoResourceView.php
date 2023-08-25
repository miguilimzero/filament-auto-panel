<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class FilamentAutoResourceView extends ViewRecord
{
    use Concerns\OverwriteFillForm;
    use Concerns\OverwriteActionInjection;

    protected function getActions(): array
    {
        return [
            ...static::getResource()::getPagesActions(),
            Actions\EditAction::make(),
        ];
    }
}
