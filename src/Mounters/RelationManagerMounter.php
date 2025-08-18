<?php

namespace Miguilim\FilamentAutoPanel\Mounters;

use Miguilim\FilamentAutoPanel\AutoRelationManager;

class RelationManagerMounter
{
    protected static array $mountedClasses = [];

    public static function makeStandalone(
        string $relation,
        array $visibleColumns,
        array $searchableColumns = [],
        ?string $recordTitleAttribute = null
    ): string {
        $relationManagerClass = AutoRelationManager::class;
        $anonymousClass =  'C' . md5(implode('N', $visibleColumns)) . "{$relation}AutoRelationManager";

        $formattedVisibleColumns = static::formatVisibleColumns($visibleColumns);
        $formattedSearchableColumns = static::formatSearchableColumns($searchableColumns);

        if (!in_array($anonymousClass, static::$mountedClasses)) {
            static::$mountedClasses[] = $anonymousClass;

            $classCode = "class {$anonymousClass} extends {$relationManagerClass} {
                protected static string \$relationship = '{$relation}';
                protected static ?string \$recordTitleAttribute = '{$recordTitleAttribute}';
                public static array \$visibleColumns = [{$formattedVisibleColumns}];
                public static array \$searchableColumns = [{$formattedSearchableColumns}];
            };";

            eval($classCode);
        }

        return $anonymousClass;
    }

    // public static function makeFromResource(
    //     string $resource,
    //     string $relation,
    //     array $visibleColumns = []
    // ): string {

    // }

    protected static function formatVisibleColumns(array $visibleColumns): string
    {
        return implode(',', array_map(fn($column) => "'{$column}'", $visibleColumns));
    }

    protected static function formatSearchableColumns(array $searchableColumns): string
    {
        return substr(str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($searchableColumns)), 1, -1);
    }

    /**
     * @deprecated use makeStandalone() instead.
     */
    public static function make(
        string $resource,
        string $relation,
        string $recordTitleAttribute,
        array $visibleColumns,
        array $searchableColumns = []
    ): string {
        return static::makeStandalone($relation, $visibleColumns, $searchableColumns, $recordTitleAttribute);
    }
}
