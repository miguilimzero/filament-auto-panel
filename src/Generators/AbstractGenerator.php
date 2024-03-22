<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Facades\Filament;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Support\Components\ViewComponent;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

abstract class AbstractGenerator
{
    use CanReadModelSchemas;
    use Concerns\HasGeneratorHelpers;

    protected Model $modelInstance;

    public function __construct(string $modelClass)
    {
        $this->modelInstance = new $modelClass();
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
                return $resource;
            }
        }

        return null;
    } 

    protected function getResourceColumns(array $exceptColumn, array $overwriteColumns, array $enumDictionary): array
    {
        $columns = [];

        foreach ($this->introspectTable()->getColumns($this->modelInstance->getTable()) as $rawColumn) {
            $column     = $this->constructColumnInstance($rawColumn);
            $columnName = $column->getName();

            // Skip specific column
            if (in_array($columnName, $exceptColumn)) {
                continue;
            }

            // Overwrite specific column
            if (isset($overwriteColumns[$columnName])) {
                $columns[$columnName] = $overwriteColumns[$columnName];
                continue;
            }

            // Is $enumDictionary column
            if (isset($enumDictionary[$columnName])) {
                $columns[$columnName] = $this->handleEnumDictionaryColumn($column, $enumDictionary[$columnName]);
                continue;
            }

            // Try to guess, match and handle relationship
            if ($guessedRelationship = $this->tryToGuessRelationshipName($column)) {
                $columns[$columnName] = $this->handleRelationshipColumn($column, $guessedRelationship[0], $guessedRelationship[1]);
                continue;
            }

            // Handle column matching type
            $columns[$columnName] = match($column->getType()) {
                'json'     => $this->handleArrayColumn($column),
                'date'     => $this->handleDateColumn($column),
                'datetime' => $this->handleDateColumn($column),
                'boolean'  => $this->handleBooleanColumn($column),
                'textarea' => $this->handleTextColumn($column),
                default    => $this->handleDefaultColumn($column),
            };
        }

        return $columns;
    }

    protected function tryToGuessRelationshipName(Column $column): ?array
    {
        if (Str::of($column->getName())->endsWith('_id')) {
            $guessedRelationshipName = $this->guessBelongsToRelationshipName($column->getName(), $this->modelInstance::class);
        
            if (filled($guessedRelationshipName)) {
                $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName(
                    column: $column->getName(), 
                    model: $this->modelInstance->{$guessedRelationshipName}()->getModel()::class
                );

                return [$guessedRelationshipName, $guessedRelationshipTitleColumnName];
            }
        }

        // TODO: Add support for morphTo relationships

        return null;
    }

    protected function constructColumnInstance(array $column): Column
    {
        // Boolean cast
        if ($column['type_name'] === 'boolean' || $column['type'] === 'tinyint(1)') {
            $column['type_name'] = 'boolean';
            $column['type']      = 'boolean';
        }

        // Json cast
        $jsonColumns = ['json', 'jsonb'];
        if (in_array($column['type_name'], $jsonColumns)) {
            $column['type_name'] = 'json';
            $column['type']      = str_replace($jsonColumns, 'json', $column['type']);
        }

        // Integer cast
        $integerColumns = [
            'integer', 'int', 'int4', 'tinyint', 'smallint', 'int2', 'mediumint', 'bigint', 'int8',
            'float', 'real', 'float4', 'double', 'float8'
        ];
        if (in_array($column['type_name'], $integerColumns)) {
            $column['type_name'] = 'integer';
            $column['type']      = str_replace($integerColumns, 'integer', $column['type']);
        }

        // Decimal cast
        $decimalColumns = ['decimal', 'numeric'];
        if (in_array($column['type_name'], $decimalColumns)) {
            $column['type_name'] = 'decimal';
            $column['type']      = str_replace($decimalColumns, 'decimal', $column['type']);
        }

        // Textarea cast
        $textColumns = ['text', 'tinytext', 'mediumtext', 'longtext'];
        if (in_array($column['type_name'], $textColumns)) {
            $originalTypeName = $column['type_name'];

            $column['type_name'] = 'textarea';
            $column['type']      = 'textarea(' . match($originalTypeName) {
                'text'      => 65_535,
                'tinytext'  => 255,
                'mediumtext'=> 16_777_215,
                'longtext'  => 4_294_967_295,
            } . ')';
        }

        // String cast
        $stringColumns = ['varchar', 'char'];
        if (in_array($column['type_name'], $stringColumns)) {
            $column['type_name'] = 'string';
            $column['type']      = str_replace($stringColumns, 'string', $column['type']);
        }

        // Datetime cast
        $datetimeColumns = ['datetime', 'timestamp'];
        if (in_array($column['type_name'], $datetimeColumns)) {
            $column['type_name'] = 'datetime';
            $column['type']      = str_replace($datetimeColumns, 'datetime', $column['type']);
        }

        return new Column($column);
    }
}