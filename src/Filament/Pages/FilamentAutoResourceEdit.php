<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceEdit extends EditRecord
{
    protected function fillForm(): void
    {
        if (static::getResource()::getIntrusive()) {
            $data = $this->getRecord()->setHidden([]);
        } else {
            $data = $this->getRecord();
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
