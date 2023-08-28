<?php

namespace Miguilim\FilamentAutoPanel\Mounters;

use Miguilim\FilamentAutoPanel\AutoRelationManager;

class RelationManagerMounter
{
    public static array $mountedClasses = [];

    public static function make(string $resource, string $relation, string $recordTitleAttribute, array $visibleColumns, array $searchableColumns = [])
    {
        $resourceName = array_reverse(explode('\\', $resource))[0];
        $anonymousClass = "{$resourceName}{$relation}RelationManager";

        $relationManagerClass = AutoRelationManager::class;

        $visibleColumns = implode(',', array_map(fn ($column) => "'{$column}'", $visibleColumns));
        $searchableColumns = str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($searchableColumns));
    
        if (! in_array($anonymousClass, static::$mountedClasses)) {
            static::$mountedClasses[] = $anonymousClass;
            eval("class {$anonymousClass} extends {$relationManagerClass} {
                protected static string \$relatedResource = $resource::class;
                protected static string \$relationship = '{$relation}';
                protected static ?string \$recordTitleAttribute = '{$recordTitleAttribute}';
                public static array \$visibleColumns = [{$visibleColumns}];
                public static array \$searchableColumns = [{$searchableColumns}];
            };");
        }

        return $anonymousClass;
    }
}