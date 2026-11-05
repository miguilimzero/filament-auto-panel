<?php

namespace Miguilim\FilamentAutoPanel\Mounters;

use Filament\Resources\Resource;
use InvalidArgumentException;
use Miguilim\FilamentAutoPanel\AutoRelationManager;


class RelationManagerMounter
{
    protected static array $mountedClasses = [];

    /**
     * @return class-string<AutoRelationManager>
     */
    public static function makeFromResource(
        string $resource,
        string $relation,
        ?bool $associateAttachActions = null,
    ): string {
        static::validateRelationship($relation);
        $resource = RuntimeClassFactory::resolveClass($resource, Resource::class);

        $properties = [
            'protected static ?string $relatedResource' => $resource,
            'protected static string $relationship' => $relation,
        ];

        if ($associateAttachActions !== null) {
            $properties['protected static bool $associateAttachActions'] = $associateAttachActions;
        }

        return static::mount($properties);
    }

    /**
     * @return class-string<AutoRelationManager>
     */
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
        static::validateRelationship($relation);

        $properties = [
            'protected static string $relationship' => $relation,
            'protected static array $enumDictionary' => $enumDictionary,
            'public static array $visibleColumns' => $visibleColumns,
            'public static array $searchableColumns' => $searchableColumns,
        ];

        foreach ([
            'protected static ?string $recordTitleAttribute' => $recordTitleAttribute,
            'protected static bool $associateAttachActions' => $associateAttachActions,
            'protected static bool $intrusive' => $intrusive,
            'protected static bool $readOnly' => $readOnly,
        ] as $declaration => $value) {
            if ($value !== null) {
                $properties[$declaration] = $value;
            }
        }

        return static::mount($properties);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return class-string<AutoRelationManager>
     */
    protected static function mount(array $properties): string
    {
        $parent = RuntimeClassFactory::resolveClass(static::getRelationManagerClass(), AutoRelationManager::class);
        $class = RuntimeClassFactory::make($parent, $properties);

        if (! in_array($class, static::$mountedClasses, true)) {
            static::$mountedClasses[] = $class;
        }

        return $class;
    }

    protected static function validateRelationship(string $relation): void
    {
        // The owner model is not available until mount; validate the method name only.
        if (! preg_match('/\A[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\z/', $relation)) {
            throw new InvalidArgumentException("Relationship [{$relation}] must be a valid method name.");
        }
    }

    protected static function getRelationManagerClass(): string
    {
        return AutoRelationManager::class;
    }
}
