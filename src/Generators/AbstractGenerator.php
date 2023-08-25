<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Doctrine\DBAL\Schema\Column;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Support\Components\ViewComponent;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoResource\Doctrine\CustomMySQLSchemaManager;

abstract class AbstractGenerator
{
    use CanReadModelSchemas;

    protected Model $modelInstance;

    public function __construct(protected string $modelClass)
    {
        $this->modelInstance = new $modelClass();
    }

    abstract protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent;

    abstract protected function handleDateColumn(Column $column): ViewComponent;

    abstract protected function handleBooleanColumn(Column $column): ViewComponent;

    abstract protected function handleTextColumn(Column $column): ViewComponent;

    abstract protected function handleDefaultColumn(Column $column): ViewComponent;

    protected function getResourceColumns(array $exceptColumn): array
    {
        $columns = [];

        foreach ($this->introspectTable()->getColumns() as $column) {
            $columnName = $column->getName();

            // Skip specific columns
            if (in_array($columnName, $exceptColumn)) {
                continue;
            }

            // Try to match relationship
            if (Str::of($columnName)->endsWith('_id')) {
                $guessedRelationshipName = $this->guessBelongsToRelationshipName($column, $this->modelInstance);

                if (filled($guessedRelationshipName)) {
                    $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName(
                        column: $column, 
                        model: $this->modelInstance->{$guessedRelationshipName}()->getModel()::class
                    );

                    $columns[$columnName] = $this->handleRelationshipColumn($column, $guessedRelationshipName, $guessedRelationshipTitleColumnName);
                    continue;
                }
            }

            // TODO: Add support for json & array cast columns

            // Handle column matching type
            $columns[$columnName] = match($column->getType()::class) {
                Types\DateType::class, Types\DateTimeType::class => $this->handleDateColumn($column),
                Types\BooleanType::class                         => $this->handleBooleanColumn($column),
                Types\TextType::class                            => $this->handleTextColumn($column),
                default                                          => $this->handleDefaultColumn($column),
            };
        }

        return $columns;
    }

    protected function introspectTable()
    {
        $doctrineConnection = $this->modelInstance
            ->getConnection()
            ->getDoctrineConnection();

        $table = (new CustomMySQLSchemaManager($doctrineConnection, $doctrineConnection->getDatabasePlatform()))
            ->introspectTable($this->modelInstance->getTable());

        return $table;
    }

    protected function isNumericColumn(Column $column)
    {
        return in_array(
            $column->getType()::class,
            [
                Types\DecimalType::class,
                Types\FloatType::class,
                Types\BigIntType::class,
                Types\IntegerType::class,
                Types\SmallIntType::class,
            ]
        );
    }
}