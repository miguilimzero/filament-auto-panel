<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Doctrine\DBAL\Schema\Column;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Support\Components\ViewComponent;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

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

    protected function getResourceColumns(array $exceptColumn, array $overwriteColumns, array $enumDictionary): array
    {
        $columns = [];

        foreach ($this->introspectTable()->getColumns() as $column) {
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

            // Try to handle column matching cast
            $this->tryToTransformColumnCast($column);

            // TODO: Add proper support for Json type
            if($column->getType() instanceof \Doctrine\DBAL\Types\JsonType) {
                continue;
            }

            // Handle column matching type
            $columns[$columnName] = match($column->getType()::class) {
                // \Doctrine\DBAL\Types\JsonType::class     => throw new \InvalidArgumentException("Column named \"{$columnName}\" is of type \"json\" and therefore cannot be decoded by Filament Auto. Use getColumnsOverwrite() to create a custom handle for it."),
                \Doctrine\DBAL\Types\ArrayType::class    => $this->handleArrayColumn($column),
                \Doctrine\DBAL\Types\DateType::class     => $this->handleDateColumn($column),
                \Doctrine\DBAL\Types\DateTimeType::class => $this->handleDateColumn($column),
                \Doctrine\DBAL\Types\BooleanType::class  => $this->handleBooleanColumn($column),
                \Doctrine\DBAL\Types\TextType::class     => $this->handleTextColumn($column),
                default                                  => $this->handleDefaultColumn($column),
            };
        }

        return $columns;
    }

    protected function tryToGuessRelationshipName(Column $column): ?array
    {
        if (Str::of($column->getName())->endsWith('_id')) {
            $guessedRelationshipName = $this->guessBelongsToRelationshipName($column, $this->modelInstance::class);
        
            if (filled($guessedRelationshipName)) {
                $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName(
                    column: $column, 
                    model: $this->modelInstance->{$guessedRelationshipName}()->getModel()::class
                );

                return [$guessedRelationshipName, $guessedRelationshipTitleColumnName];
            }
        }

        // TODO: Add support for morphTo relationships

        return null;
    }

    protected function tryToTransformColumnCast(Column $column): void
    {
        $columnCast = $this->modelInstance->getCasts()[$column->getName()] ?? null;

        if (! $columnCast) {
            return;
        }

        // Array cast
        if ($columnCast === 'array') {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('array'));
        }

        // Boolean cast
        if ($columnCast === 'boolean') {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('boolean'));
        }

        // Date casts
        if (str_starts_with($columnCast, 'date')) {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('date'));
        }
        if (str_starts_with($columnCast, 'datetime')) {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('datetime'));
        }

        // Numeric casts
        if (str_starts_with($columnCast, 'decimal')) {
            $precision = explode(':', $columnCast)[1] ?? null;

            if ($precision !== null) {
                $column->setType(\Doctrine\DBAL\Types\Type::getType('decimal'));
                $column->setPrecision($precision);
            } else {
                $column->setType(\Doctrine\DBAL\Types\Type::getType('integer'));
            }
        }
        if ($columnCast === 'integer') {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('integer'));
        }
        if (
            (! ($column->getType() instanceof \Doctrine\DBAL\Types\DecimalType))
            && ($columnCast === 'double' || $columnCast === 'float' || $columnCast === 'real')
        ) {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('integer')); // Set as integer as there is no way to know the precision
        }

        // Object casts
        if ($columnCast === 'collection' || $columnCast === 'object' || $columnCast === 'json') {
            $column->setType(\Doctrine\DBAL\Types\Type::getType('json'));
        }
    }
}