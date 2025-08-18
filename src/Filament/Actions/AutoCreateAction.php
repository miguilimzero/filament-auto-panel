<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Filament\Actions\CreateAction;
use Miguilim\FilamentAutoPanel\AutoResource;

class AutoCreateAction extends CreateAction
{
    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = false;

    public bool $showOnListPage = true;

    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->using(function (array $data, AutoResource $resource) {
                if ($resource::isIntrusive()) {
                    $this->getModel()::forceCreate($data);
                } else {
                    $this->getModel()::create($data);
                }
            });
    }
}
