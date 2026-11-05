<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

class RelationshipGuesser
{
    /**
     * @return string[]
     */
    public static function guessBelongsTo(string $column, Model $model): array
    {
        if (! str_ends_with($column, '_id')) {
            return [];
        }

        foreach (static::relationshipNames(substr($column, 0, -3)) as $name) {
            $relationship = static::resolveRelationship($model, $name);

            if ($relationship instanceof BelongsTo && ! $relationship instanceof MorphTo && $relationship->getForeignKeyName() === $column) {
                return [$name, static::guessTitleColumnName($column, $model, $name)];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function guessMorphTo(string $column, Model $model): array
    {
        if (! str_ends_with($column, '_type')) {
            return [];
        }

        $prefix = substr($column, 0, -5);

        foreach (static::relationshipNames($prefix) as $name) {
            $relationship = static::resolveRelationship($model, $name);

            if ($relationship instanceof MorphTo && $relationship->getMorphType() === $column && $relationship->getForeignKeyName() === "{$prefix}_id") {
                return [$name, static::guessTitleColumnName($column, $model, $name)];
            }
        }

        return [];
    }

    public static function guessTitleColumnName(string $column, Model $model, string $relationshipName): string
    {
        $related = $model->{$relationshipName}()->getRelated();
        $schema = $related->getConnection()->getSchemaBuilder();
        $table = $related->getTable();
        $columns = $schema->getColumnListing($table);

        foreach (['name', 'title'] as $candidate) {
            if (in_array($candidate, $columns)) {
                return $candidate;
            }
        }

        foreach ($schema->getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'][0] ?? $related->getKeyName();
            }
        }

        return $related->getKeyName();
    }

    public static function getRecordTitle(?Model $record): mixed
    {
        if ($record === null) {
            return null;
        }

        foreach (['name', 'title'] as $attribute) {
            if ($record->hasAttribute($attribute) && ($value = $record->getAttribute($attribute)) !== null) {
                return $value;
            }
        }

        return $record->getKey();
    }

    protected static function relationshipNames(string $prefix): array
    {
        return array_unique([$prefix, Str::camel($prefix)]);
    }

    protected static function resolveRelationship(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        $method = new ReflectionMethod($model, $name);

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || $method->getDeclaringClass()->getName() === Model::class) {
            return null;
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && ! is_a($returnType->getName(), Relation::class, true)) {
            return null;
        }

        $relationship = Relation::noConstraints(fn () => $model->{$name}());

        return $relationship instanceof Relation ? $relationship : null;
    }
}
