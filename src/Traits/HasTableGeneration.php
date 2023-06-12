<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Doctrine\DBAL\Types;
use Filament\Tables;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Illuminate\Support\Str;

trait HasTableGeneration
{
    public static function makeTableSchema(string $model, array $visibleColumns, array $except = []): array
    {
        return (new self())->getResourceTableSchema($model, $visibleColumns, $except);
    }

    protected function getResourceTableSchema(string $model, array $visibleColumns, array $except): array
    {
        $columns = $this->getResourceTableSchemaColumns($model);

        $dummyModel = new $model;
        $columnInstances = [];

        foreach ($columns as $key => $value) {
            if (in_array($value['original_name'][0] ?? $key, $except)) {
                continue;
            }
            unset($value['original_name']);

            $columnInstance = call_user_func([$value['type'], 'make'], $key);

            foreach ($value as $valueName => $parameters) {
                if($valueName === 'type') {
                    continue;
                }
                
                $columnInstance->{$valueName}(...$parameters);
            }

            if($dummyModel->getKeyName() === $key) {
                $columnInstance->searchable();
            } else {
                $columnInstance->toggleable(
                    isToggledHiddenByDefault: ! in_array($key, $visibleColumns)
                );
            }

            $columnInstances[] = $columnInstance;
        }

        return $columnInstances;
    }

    protected function getResourceTableSchemaColumns(string $model): array
    {
        $table = $this->introspectTable($model);

        $columns = [];

        foreach ($table->getColumns() as $column) {
            // if ($column->getAutoincrement()) {
            //     continue;
            // }

            $columnName = $column->getName();

            if (Str::of($columnName)->endsWith([
                '_token',
            ])) {
                continue;
            }

            if (Str::of($columnName)->contains([
                'password',
            ])) {
                continue;
            }

            $columnData = [];

            if ($column->getType() instanceof Types\BooleanType) {
                $columnData['type'] = Tables\Columns\IconColumn::class;
                $columnData['boolean'] = [];
            } else {
                $columnData['type'] = Tables\Columns\TextColumn::class;

                if ($column->getType()::class === Types\DateType::class) {
                    $columnData['date'] = [];
                }

                if ($column->getType()::class === Types\DateTimeType::class) {
                    $columnData['dateTime'] = [];
                }
            }

            if (Str::of($columnName)->endsWith('_id')) {
                $guessedRelationshipName = $this->guessBelongsToRelationshipName($column, $model);

                if (filled($guessedRelationshipName)) {
                    $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName($column, app($model)->{$guessedRelationshipName}()->getModel()::class);

                    $columnData['original_name'] = [$columnName];
                    $columnName = "{$guessedRelationshipName}.{$guessedRelationshipTitleColumnName}";
                }
            }

            $columns[$columnName] = $columnData;
        }

        return $columns;
    }
}