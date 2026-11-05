<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\ListRecords;

class AutoResourceListRecords extends ListRecords
{
    public function getTabs(): array
    {
        return static::getResource()::getTabs();
    }

    protected function getHeaderWidgets(): array
    {
        return static::getResource()::getHeaderWidgets()['list'] ?? [];
    }

    protected function getFooterWidgets(): array
    {
        return static::getResource()::getFooterWidgets()['list'] ?? [];
    }

    protected function getHeaderActions(): array
    {
        return static::getResource()::getListPageActions();
    }
}
