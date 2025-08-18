<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Exception;
use Filament\Actions\CreateAction;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Support\Arr;
use Filament\Actions\Contracts\HasActions;
use Miguilim\FilamentAutoPanel\AutoResource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class AutoCreateAction extends CreateAction
{
    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = false;

    public bool $showOnListPage = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (array $arguments, Schema $schema, AutoResource $resource): void {
            if ($arguments['another'] ?? false) {
                throw new Exception('Another in $arguments is not supported');
            }

            $model = $this->getModel();

            $record = $this->process(function (array $data, HasActions & HasSchemas $livewire) use ($model, $resource): Model {
                $relationship = $this->getRelationship();

                $pivotData = [];

                if ($relationship instanceof BelongsToMany) {
                    $pivotColumns = $relationship->getPivotColumns();

                    $pivotData = Arr::only($data, $pivotColumns);
                    $data = Arr::except($data, $pivotColumns);
                }

                if ($translatableContentDriver = $livewire->makeFilamentTranslatableContentDriver()) {
                    $record = $translatableContentDriver->makeRecord($model, $data);
                } else {
                    $record = new $model;

                    if ($resource::isIntrusive()) {
                        $record->forceFill($data);
                    } else {
                        $record->fill($data);
                    }
                }

                if (
                    (! $relationship) ||
                    ($relationship instanceof HasOneOrManyThrough)
                ) {
                    $record->save();

                    return $record;
                }

                if ($relationship instanceof BelongsToMany) {
                    $relationship->save($record, $pivotData);

                    return $record;
                }

                /** @phpstan-ignore-next-line */
                $relationship->save($record);

                return $record;
            });

            $this->record($record);
            $schema->model($record)->saveRelationships();

            $this->success();
        });
    }
}
