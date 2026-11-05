<?php

namespace Miguilim\FilamentAutoPanel\Concerns;

trait HasAutoPanelConfiguration
{
    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $intrusive = true;

    protected static bool $readOnly = false;

    protected static function mapColumnOverrides(array $columns): array
    {
        $mapped = [];

        foreach ($columns as $column) {
            $mapped[$column->getName()] = $column;
        }

        return $mapped;
    }
}
