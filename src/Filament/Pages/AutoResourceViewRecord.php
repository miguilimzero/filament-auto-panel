<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class AutoResourceViewRecord extends ViewRecord
{
    protected function getHeaderWidgets(): array
    {
        return [
            ...static::getResource()::getHeaderWidgets()['view'],
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ...static::getResource()::getFooterWidgets()['view'],
        ];
    }

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
        $actions = [...static::getResource()::getViewPageActions()];

        if (!static::getResource()::getReadOnly()) {
            $actions = [
                ...$actions,
                $this->makeEditAction(),
                DeleteAction::make(),
            ];

            if (method_exists(static::getResource()::getModel(), 'bootSoftDeletes')) {
                $actions = [
                    ...$actions,
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ];
            }
        }

        return $actions;
    }

    protected function makeEditAction()
    {
        return EditAction::make()
            ->fillForm(function (Model $record): array {
                if (static::getResource()::getIntrusive()) {
                    return $record->setHidden([])->attributesToArray();
                } else {
                    return $record->attributesToArray();
                }
            })->using(function (array $data, Model $record) {
                if (static::getResource()::getIntrusive()) {
                    $record->forceFill($data)->save();
                } else {
                    $record->update($data);
                }
            });
    }
}
