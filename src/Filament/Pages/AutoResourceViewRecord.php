<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\ViewRecord;
use Miguilim\FilamentAutoPanel\Support\FormData;

class AutoResourceViewRecord extends ViewRecord
{
    protected function getAllRelationManagers(): array
    {
        return static::getResource()::withAutomaticRelations(parent::getAllRelationManagers());
    }

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

        $this->callHook('beforeFill');

        FormData::fill(
            $this->form,
            $this->mutateFormDataBeforeFill($record->attributesToArray()),
            static::getResource()::getExcludedColumns(),
        );

        $this->callHook('afterFill');
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
            array_diff_key(
                $this->mutateFormDataBeforeFill($record->attributesToArray()),
                array_flip(static::getResource()::getExcludedColumns()),
            ),
            $statePaths,
        );
    }

    protected function getHeaderActions(): array
    {
        return static::getResource()::getViewPageActions();
    }
}
