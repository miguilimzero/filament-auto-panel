<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceEdit extends EditRecord
{
    protected function fillForm(): void
    {
        if (static::getResource()::getIntrusive()) {
            $record = $this->getRecord()->setHidden([]);
        } else {
            $record = $this->getRecord();
        }

        $this->fillFormWithDataAndCallHooks($record);
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
