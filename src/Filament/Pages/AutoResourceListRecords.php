<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\ListRecords;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;

class AutoResourceListRecords extends ListRecords
{
    public function getTabs(): array
    {
        return [
            ...static::getResource()::getTabs()
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ...static::getResource()::getHeaderWidgets()['list'],
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ...static::getResource()::getFooterWidgets()['list'],
        ];
    }

    protected function getActions(): array
    {
        $actions = [...static::getResource()::getListPageActions()];

        if (! static::getResource()::getReadOnly()) {
            $actions[] = AutoCreateAction::make();
        }

        return $actions;
    }
}
