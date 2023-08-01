<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Miguilim\FilamentAutoResource\AutoRelationManager;

class RelationManagerGenerator
{
    public static array $generatedRelationClasses = [];

    public static function makeRelationManager(string $resource, string $relation, string $recordTitleAttribute, array $visibleColumns, array $searchableColumns = [])
    {
        $resourceName = array_reverse(explode('\\', $resource))[0];
        $anonymousClass = "{$resourceName}{$relation}RelationManager";

        $relationManagerClass = AutoRelationManager::class;

        $visibleColumns = implode(',', array_map(fn ($column) => "'{$column}'", $visibleColumns));
        $searchableColumns = implode(',', array_map(fn ($column) => "'{$column}'", $searchableColumns));
    
        if (! in_array($anonymousClass, static::$generatedRelationClasses)) {
            static::$generatedRelationClasses[] = $anonymousClass;
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