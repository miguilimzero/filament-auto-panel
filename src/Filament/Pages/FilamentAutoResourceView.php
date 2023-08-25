<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceView extends ViewRecord
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

    protected function getActions(): array
    {
        if (method_exists(static::getResource()::getModel(), 'bootSoftDeletes')) {
            return [
                ...static::getResource()::getPagesActions(),
                $this->makeEditAction(),
                Actions\DeleteAction::make(),
                Actions\ForceDeleteAction::make(),
                Actions\RestoreAction::make(),
            ];
        }

        return [
            ...static::getResource()::getPagesActions(),
            $this->makeEditAction(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function makeEditAction()
    {
        return Actions\EditAction::make()
            ->fillForm(function (Model $record): array {
                if (static::getResource()::getIntrusive()) {
                    return $record->setHidden([])->attributesToArray();
                } else {
                    return $record->attributesToArray();
                }
            })->using(function (array $data, Model $record) {
                if (static::getResource()::getIntrusive()) {
                    foreach ($data as $key => $value) {
                        $record->{$key} = $value;
                    }
                    $record->save();
                } else {
                    $record->update($data);
                }
            });
    }
}
