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

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        if (static::getResource()::getIntrusive()) {
            $data = $this->getRecord()->setHidden([])->attributesToArray();
        } else {
            $data = $this->getRecord()->attributesToArray();
        }

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);

        $this->callHook('afterFill');
    }
}
