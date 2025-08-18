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
        array $enumDictionary = [],
        ?string $recordTitleAttribute = null,
        ?bool $associateAttachActions = null,
        ?bool $intrusive = null,
        ?bool $readOnly = null,
    ): string {
        $relationManagerClass = static::getRelationManagerClass();
        $anonymousClass =  'C' . md5(implode('N', $visibleColumns)) . "{$relation}AutoRelationManager";

        $formattedVisibleColumns = static::formatVisibleColumns($visibleColumns);
        $formattedSearchableColumns = static::formatSearchableColumns($searchableColumns);
        $formattedEnumDictionary = static::formatEnumDictionary($enumDictionary);

        $recordTitleAttributeCode = static::generateOptionalParameterCode('?string', 'recordTitleAttribute', $recordTitleAttribute);
        $associateAttachActionsCode = static::generateOptionalParameterCode('bool', 'associateAttachActions', $associateAttachActions);
        $intrusiveCode = static::generateOptionalParameterCode('bool', 'intrusive', $intrusive);
        $readOnlyCode = static::generateOptionalParameterCode('bool', 'readOnly', $readOnly);

        if (!in_array($anonymousClass, static::$mountedClasses)) {
            static::$mountedClasses[] = $anonymousClass;

            $classCode = trim("class {$anonymousClass} extends {$relationManagerClass} {
                protected static string \$relationship = '{$relation}';
                protected static array \$enumDictionary = [{$formattedEnumDictionary}];
                public static array \$visibleColumns = [{$formattedVisibleColumns}];
                public static array \$searchableColumns = [{$formattedSearchableColumns}];
                {$recordTitleAttributeCode}
                {$associateAttachActionsCode}
                {$intrusiveCode}
                {$readOnlyCode}
            };");

            eval($classCode);
        }

        return $anonymousClass;
    }

    public static function makeFromResource(
        string $resource,
        string $relation,
        ?string $recordTitleAttribute = null,
        ?bool $associateAttachActions = null,
        ?bool $intrusive = null,
        ?bool $readOnly = null,
    ): string {
        $relationManagerClass = static::getRelationManagerClass();
        $resourceName = array_reverse(explode('\\', $resource))[0];
        $anonymousClass = "{$resourceName}{$relation}AutoRelationManager";

        $recordTitleAttributeCode = static::generateOptionalParameterCode('?string', 'recordTitleAttribute', $recordTitleAttribute);
        $associateAttachActionsCode = static::generateOptionalParameterCode('bool', 'associateAttachActions', $associateAttachActions);
        $intrusiveCode = static::generateOptionalParameterCode('bool', 'intrusive', $intrusive);
        $readOnlyCode = static::generateOptionalParameterCode('bool', 'readOnly', $readOnly);

        if (!in_array($anonymousClass, static::$mountedClasses)) {
            static::$mountedClasses[] = $anonymousClass;

            $classCode = trim("class {$anonymousClass} extends {$relationManagerClass} {
                protected static ?string \$relatedResource = {$resource}::class;
                protected static string \$relationship = '{$relation}';
                {$recordTitleAttributeCode}
                {$associateAttachActionsCode}
                {$intrusiveCode}
                {$readOnlyCode}
            };");

            eval($classCode);
        }

        return $anonymousClass;
    }

    protected static function formatVisibleColumns(array $visibleColumns): string
    {
        return implode(',', array_map(fn($column) => "'{$column}'", $visibleColumns));
    }

    protected static function formatSearchableColumns(array $searchableColumns): string
    {
        return substr(str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($searchableColumns)), 1, -1);
    }

    protected static function formatEnumDictionary(array $enumDictionary): string
    {
        return substr(str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($enumDictionary)), 1, -1);
    }

    protected static function generateOptionalParameterCode(string $type, string $name, string|bool|null $value): string
    {
        if ($value !== null) {
            if ($type === 'string' || $type === '?string') {
                $value = "'{$value}'";
            }
            if ($type === 'bool') {
                $value = $value ? 'true' : 'false';
            }

            return "protected static {$type} \${$name} = $value;";
        }

        return '';
    }

    protected static function getRelationManagerClass(): string
    {
        return AutoRelationManager::class;
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
        return static::makeStandalone($relation, $visibleColumns, $searchableColumns, [], $recordTitleAttribute);
    }
}
