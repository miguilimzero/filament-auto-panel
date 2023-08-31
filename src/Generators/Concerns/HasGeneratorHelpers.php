<?php

namespace Miguilim\FilamentAutoPanel\Generators\Concerns;

use Closure;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Illuminate\Support\HtmlString;
use Laravel\SerializableClosure\SerializableClosure;
use Miguilim\FilamentAutoPanel\Doctrine\CustomMySQLSchemaManager;

trait HasGeneratorHelpers
{
    protected static array $generatedSchemas = [];

    protected function introspectTable(): Table
    {
        $doctrineConnection = $this->modelInstance
            ->getConnection()
            ->getDoctrineConnection();

        $table = (new CustomMySQLSchemaManager($doctrineConnection, $doctrineConnection->getDatabasePlatform()))
            ->introspectTable($this->modelInstance->getTable());

        return $table;
    }

    protected function isNumericColumn(Column $column): bool
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

    protected function getColumnDecimalPlaces(Column $column): ?int
    {
        if (! ($column->getType() instanceof \Doctrine\DBAL\Types\DecimalType)) {
            return null;
        }

        return $column->getScale();
    }

    protected function placeholderHtml(string $placeholder = 'null'): HtmlString
    {
        return new HtmlString("<span class=\"text-gray-500 dark:text-gray-400\">{$placeholder}</span>");
    }

    protected static function getCachedSchema(array $parameters, Closure $function): array
    {
        $cacheKey = md5(json_encode($parameters) . static::class);

        return static::$generatedSchemas[$cacheKey] ??= $function();
    }
}