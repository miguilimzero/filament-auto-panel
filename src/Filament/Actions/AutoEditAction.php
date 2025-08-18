<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Filament\Actions\EditAction;
use Miguilim\FilamentAutoPanel\AutoResource;
use Illuminate\Database\Eloquent\Model;

class AutoEditAction extends EditAction
{
    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = true;

    public bool $showOnListPage = false;

    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->fillForm(function (Model $record, AutoResource $resource): array {
                if ($resource::isIntrusive()) {
                    return $record->setHidden([])->attributesToArray();
                } else {
                    return $record->attributesToArray();
                }
            })->using(function (array $data, Model $record, AutoResource $resource) {
                if ($resource::isIntrusive()) {
                    $record->forceFill($data)->save();
                } else {
                    $record->update($data);
                }
            });
    }
}
