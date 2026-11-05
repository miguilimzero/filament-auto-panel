<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Miguilim\FilamentAutoPanel\Generators\Concerns\HasGeneratorHelpers;
use Filament\Facades\Filament;
use Filament\Support\Components\ViewComponent;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

abstract class AbstractGenerator
{
    use HasGeneratorHelpers;

    protected Model $modelInstance;

    public function __construct(string|Model $modelClass)
    {
        $this->modelInstance = $modelClass instanceof Model ? $modelClass : new $modelClass();
    }

    abstract protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent;

    abstract protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent;

    abstract protected function handleArrayColumn(Column $column): ViewComponent;

    abstract protected function handleDateColumn(Column $column): ViewComponent;

    abstract protected function handleBooleanColumn(Column $column): ViewComponent;

    abstract protected function handleTextColumn(Column $column): ViewComponent;

    abstract protected function handleDefaultColumn(Column $column): ViewComponent;

    abstract protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array;

    public static function tryToGuessRelatedResource(Model $relatedRecord): ?string
    {
        foreach (Filament::getResources() as $resource) {
            if ($relatedRecord instanceof ($resource::getModel())) {
                if (! $resource::canAccess()) {
                    continue;
                }

                return $resource;
            }
        }

        return null;
    }

    protected function getResourceColumns(array $exceptColumn, array $overwriteColumns, array $enumDictionary): array
    {
        $columns = [];
        $metadata = [];

        foreach ($this->introspectTable()->getColumns($this->modelInstance->getTable()) as $rawColumn) {
            $column = $this->constructColumnInstance($rawColumn);
            $metadata[$column->getName()] = $column;
        }

        $morphRelationships = [];

        if (! $this instanceof FormGenerator) {
            foreach ($metadata as $name => $column) {
                if (! str_ends_with($name, '_type') || ! isset($metadata[substr($name, 0, -5) . '_id'])) {
                    continue;
                }

                $relationship = RelationshipGuesser::guessMorphTo($name, $this->modelInstance);

                if ($relationship) {
                    $morphRelationships[$name] = $relationship;
                }
            }
        }

        foreach ($metadata as $columnName => $column) {
            if (in_array($columnName, $exceptColumn)) {
                continue;
            }

            if (isset($overwriteColumns[$columnName])) {
                $columns[$columnName] = $overwriteColumns[$columnName];
                continue;
            }

            if (isset($enumDictionary[$columnName])) {
                $columns[$columnName] = $this->handleEnumDictionaryColumn($column, $enumDictionary[$columnName]);
                continue;
            }

            if (isset($morphRelationships[$columnName])) {
                [$name, $title] = $morphRelationships[$columnName];
                $columns[$name] = $this->handleRelationshipColumn($column, $name, $title);
                continue;
            }

            if (str_ends_with($columnName, '_id')) {
                if (isset($morphRelationships[substr($columnName, 0, -3) . '_type'])) {
                    continue;
                }

                $relationship = RelationshipGuesser::guessBelongsTo($columnName, $this->modelInstance);

                if ($relationship) {
                    $columns[$columnName] = $this->handleRelationshipColumn($column, $relationship[0], $relationship[1]);
                    continue;
                }
            }

            $columns[$columnName] = match ($column->getType()) {
                'json' => $this->handleArrayColumn($column),
                'date', 'datetime' => $this->handleDateColumn($column),
                'boolean' => $this->handleBooleanColumn($column),
                'textarea' => $this->handleTextColumn($column),
                default => $this->handleDefaultColumn($column),
            };
        }

        return $columns;
    }

    protected function constructColumnInstance(array $column): Column
    {
        return new Column(
            $column,
            $this->modelInstance->getCasts()[$column['name']] ?? null,
            $this->modelInstance->getConnection()->getDriverName(),
        );
    }
}
