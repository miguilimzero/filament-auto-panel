<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Miguilim\FilamentAutoResource\AutoRelationManager;

trait HasRelationManagerGeneration
{
    public static array $createdRelationClasses = [];

    public static function makeRelationManager(string $resource, string $relation, string $recordTitleAttribute, array $visibleColumns)
    {
        $resourceName = array_reverse(explode('\\', $resource))[0];
        $anonymousClass = "{$resourceName}{$relation}RelationManager";

        $relationManagerClass = AutoRelationManager::class;

        $visibleColumns = implode(',', array_map(fn ($column) => "'{$column}'", $visibleColumns));
    
        if (! in_array($anonymousClass, static::$createdRelationClasses)) {
            static::$createdRelationClasses[] = $anonymousClass;
            eval("class {$anonymousClass} extends {$relationManagerClass} {
                protected static string \$relatedResource = $resource::class;
                protected static string \$relationship = '{$relation}';
                protected static ?string \$recordTitleAttribute = '{$recordTitleAttribute}';
                public static array \$visibleColumns = [{$visibleColumns}];
            };");
        }

        return $anonymousClass;
    }
}