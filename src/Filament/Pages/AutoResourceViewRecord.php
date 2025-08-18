<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;

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
        $record = static::getResource()::getIntrusive()
            ? $this->getRecord()->setHidden([])
            : $this->getRecord();

        $this->fillFormWithDataAndCallHooks($record);
    }

    /**
     * @param  array<string>  $statePaths
     */
    public function refreshFormData(array $statePaths): void
    {
        $record = static::getResource()::getIntrusive()
            ? $this->getRecord()->setHidden([])
            : $this->getRecord();

        $this->form->fillPartially(
            $this->mutateFormDataBeforeFill($record->attributesToArray()),
            $statePaths,
        );
    }

    protected function getActions(): array
    {
        $actions = [...static::getResource()::getViewPageActions()];

        if (!static::getResource()::getReadOnly()) {
            $actions = [
                ...$actions,
                AutoEditAction::make(),
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
}
