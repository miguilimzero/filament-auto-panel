<?php

namespace Miguilim\FilamentAuto\Generators\Concerns;

use Closure;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Support\HtmlString;
use Laravel\SerializableClosure\SerializableClosure;
use Miguilim\FilamentAuto\Doctrine\CustomMySQLSchemaManager;

trait HasGeneratorHelpers
{
    protected static array $generatedSchemas = [];

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

    protected function placeholderHtml(string $placeholder = 'null'): HtmlString
    {
        return new HtmlString("<span class=\"text-gray-500 dark:text-gray-400\">{$placeholder}</span>");
    }

    protected static function getCachedSchema(Closure $function): array
    {
        $cacheKey = md5(serialize(new SerializableClosure($function)) . static::class);

        return static::$generatedSchemas[$cacheKey] ??= $function();
    }
}