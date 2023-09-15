<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceEdit extends EditRecord
{
    protected function fillForm(): void
    {
        if (static::getResource()::getIntrusive()) {
            $data = $this->getRecord()->setHidden([])->attributesToArray();
        } else {
            $data = $this->getRecord()->attributesToArray();
        }

        $this->fillFormWithDataAndCallHooks($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (static::getResource()::getIntrusive()) {
            $record->forceFill($data)->save();
        } else {
            $record->update($data);
        }

        return $record;
    }
}
