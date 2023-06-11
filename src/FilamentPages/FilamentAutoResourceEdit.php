<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions;

class FilamentAutoResourceEdit extends EditRecord
{
    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
