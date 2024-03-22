<?php

namespace Miguilim\FilamentAutoPanel\Generators\Concerns;

use Closure;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Schema\Builder;

trait HasGeneratorHelpers
{
    protected static array $generatedSchemas = [];

    protected function introspectTable(): Builder
    {
        return $this->modelInstance
            ->getConnection()
            ->getSchemaBuilder();
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