<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class FilamentAutoResourceList extends ListRecords
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
        return [
            Actions\CreateAction::make(),
        ];
    }
}
