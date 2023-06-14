<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceEdit extends EditRecord
{
    use Concerns\OverwriteFillForm;
    use Concerns\OverwriteActionInjection;

    protected function getActions(): array
    {
        if (method_exists(static::getResource()::getModel(), 'bootSoftDeletes')) {
            return [
                ...static::getResource()::getPagesActions(),
                Actions\DeleteAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ];
        }

        return [
            ...static::getResource()::getPagesActions(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (static::getResource()::getIntrusive()) {
            foreach ($data as $key => $value) {
                $record->{$key} = $value;
            }
            $record->save();

            return $record;
        }

        $record->update($data);

        return $record;
    }
}
