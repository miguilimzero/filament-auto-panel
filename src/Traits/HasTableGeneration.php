<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Doctrine\DBAL\Types;
use Filament\Tables;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Illuminate\Support\Str;

trait HasTableGeneration
{
    public static function makeTableSchema(string $model, array $tableColumns): array
    {
        return (new self())->getResourceTableSchema($model, $tableColumns);
    }

    protected function getResourceTableSchema(string $model, array $tableColumns): array
    {
        $columns = $this->getResourceTableSchemaColumns($model);

        $columnInstances = [];

        foreach ($columns as $key => $value) {
            $columnInstance = call_user_func([$value['type'], 'make'], $key);

            foreach ($value as $valueName => $parameters) {
                if($valueName === 'type') {
                    continue;
                }
                
                $columnInstance->{$valueName}(...$parameters);
            }

            $columnInstances[$key] = $columnInstance;
        }

        // Re-order columns based on $tableColumns resource array
        $finalColumns = [];

        foreach ($tableColumns as $column) {
            if (! isset($columnInstances[$column])) {
                continue;
            }

            $finalColumns[] = $columnInstances[$column];
        }

        return $finalColumns;
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

                    $columnName = "{$guessedRelationshipName}.{$guessedRelationshipTitleColumnName}";
                }
            }

            $columns[$columnName] = $columnData;
        }

        return $columns;
    }
}