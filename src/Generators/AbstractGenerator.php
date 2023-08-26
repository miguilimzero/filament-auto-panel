<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Closure;
use Doctrine\DBAL\Schema\Column;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Support\Components\ViewComponent;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;
use Laravel\SerializableClosure\SerializableClosure;
use Miguilim\FilamentAutoResource\Doctrine\CustomMySQLSchemaManager;

abstract class AbstractGenerator
{
    use CanReadModelSchemas;
    
    protected static array $generatedSchemas = [];

    protected Model $modelInstance;

    public function __construct(string $modelClass)
    {
        $this->modelInstance = new $modelClass();
    }

    abstract protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent;

    abstract protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent;

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

            // TODO: Add support for json & array cast columns
            // Skip non-supported column casts
            if (in_array($this->modelInstance->getCasts()[$column->getName()] ?? '', ['json', 'array'])) {
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

            // TODO: Add support for morph relationships
            // Try to match relationship
            if (Str::of($columnName)->endsWith('_id')) {
                $guessedRelationshipName = $this->guessBelongsToRelationshipName($column, $this->modelInstance::class);

                if (filled($guessedRelationshipName)) {
                    $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName(
                        column: $column, 
                        model: $this->modelInstance->{$guessedRelationshipName}()->getModel()::class
                    );

                    $columns[$columnName] = $this->handleRelationshipColumn($column, $guessedRelationshipName, $guessedRelationshipTitleColumnName);
                    continue;
                }
            }

            // Handle column matching type
            $columns[$columnName] = match($column->getType()::class) {
                \Doctrine\DBAL\Types\DateType::class     => $this->handleDateColumn($column),
                \Doctrine\DBAL\Types\DateTimeType::class => $this->handleDateColumn($column),
                \Doctrine\DBAL\Types\BooleanType::class  => $this->handleBooleanColumn($column),
                \Doctrine\DBAL\Types\TextType::class     => $this->handleTextColumn($column),
                default                                  => $this->handleDefaultColumn($column),
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
                \Doctrine\DBAL\Types\DecimalType::class,
                \Doctrine\DBAL\Types\FloatType::class,
                \Doctrine\DBAL\Types\BigIntType::class,
                \Doctrine\DBAL\Types\IntegerType::class,
                \Doctrine\DBAL\Types\SmallIntType::class,
            ]
        );
    }

    protected static function getCachedSchema(Closure $function): array
    {
        $cacheKey = md5(serialize(new SerializableClosure($function)) . static::class);

        return static::$generatedSchemas[$cacheKey] ??= $function();
    }

    protected function placeholderHtml(string $placeholder = 'null'): HtmlString
    {
        return new HtmlString("<span class=\"text-gray-500 dark:text-gray-400\">{$placeholder}</span>");
    }
}