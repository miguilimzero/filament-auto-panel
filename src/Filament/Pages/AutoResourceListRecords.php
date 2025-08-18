<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
            $actions[] = CreateAction::make()
                ->using(function (array $data) {
                    if (static::getResource()::getIntrusive()) {
                        $this->getModel()::forceCreate($data);
                    } else {
                        $this->getModel()::create($data);
                    }
                });
        }

        return $actions;
    }
}
