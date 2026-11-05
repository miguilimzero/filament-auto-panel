<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\ViewRecord;

class AutoResourceViewRecord extends ViewRecord
{
    protected function getHeaderWidgets(): array
    {
        return static::getResource()::getHeaderWidgets()['view'] ?? [];
    }

    protected function getFooterWidgets(): array
    {
        return static::getResource()::getFooterWidgets()['view'] ?? [];
    }

    protected function fillForm(): void
    {
        $record = static::getResource()::isIntrusive()
            ? (clone $this->getRecord())->setHidden([])
            : $this->getRecord();

        $this->fillFormWithDataAndCallHooks($record);
    }

    /**
     * @param  array<string>  $statePaths
     */
    public function refreshFormData(array $statePaths): void
    {
        $record = static::getResource()::isIntrusive()
            ? (clone $this->getRecord())->setHidden([])
            : $this->getRecord();

        $this->form->fillPartially(
            $this->mutateFormDataBeforeFill($record->attributesToArray()),
            $statePaths,
        );
    }

    protected function getHeaderActions(): array
    {
        return static::getResource()::getViewPageActions();
    }
}
