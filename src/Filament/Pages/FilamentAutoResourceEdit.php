<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceEdit extends EditRecord
{
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
