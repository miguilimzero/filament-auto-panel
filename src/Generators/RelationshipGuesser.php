<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
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
        $name = static::guessBelongsToRelationshipName($column, $model);

        return $name === null ? [] : [$name, static::guessTitleColumnName($column, $model, $name)];
    }

    public static function guessBelongsToRelationshipName(string $column, Model $model): ?string
    {
        if (! str_ends_with($column, '_id')) {
            return null;
        }

        foreach (static::relationshipNames(substr($column, 0, -3)) as $name) {
            $relationship = static::resolveRelationship($model, $name);

            if ($relationship instanceof BelongsTo && ! $relationship instanceof MorphTo && $relationship->getForeignKeyName() === $column) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public static function guessMorphTo(string $column, Model $model): array
    {
        $name = static::guessMorphToRelationshipName($column, $model);

        return $name === null ? [] : [$name, static::guessTitleColumnName($column, $model, $name)];
    }

    public static function guessMorphToRelationshipName(string $column, Model $model): ?string
    {
        if (! str_ends_with($column, '_type')) {
            return null;
        }

        $prefix = substr($column, 0, -5);
        $model = clone $model;
        $model->setAttribute($column, null);

        foreach (static::relationshipNames($prefix) as $name) {
            $relationship = static::resolveRelationship($model, $name);

            if ($relationship instanceof MorphTo && $relationship->getMorphType() === $column && $relationship->getForeignKeyName() === "{$prefix}_id") {
                return $name;
            }
        }

        return null;
    }

    public static function guessTitleColumnName(string $column, Model $model, string $relationshipName): string
    {
        return static::guessModelTitleColumnName($model->{$relationshipName}()->getRelated());
    }

    public static function guessModelTitleColumnName(Model $related): string
    {
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

    /** @return list<string> */
    public static function guessSearchColumns(Model $model, string $relationshipName, string $titleColumn): array
    {
        return static::guessModelSearchColumns($model->{$relationshipName}()->getRelated(), $titleColumn);
    }

    /** @return list<string> */
    public static function guessModelSearchColumns(Model $related, string $titleColumn): array
    {
        $connection = $related->getConnection();
        $columns = $connection->getSchemaBuilder()->getColumnListing($related->getTable());
        $casts = $related->getCasts();
        $searchColumns = [$titleColumn];

        foreach (array_unique([$related->getKeyName(), 'id', 'name', 'title', 'email']) as $name) {
            if (! in_array($name, $columns, true)
                || in_array($name, $related->getHidden(), true)
                || ($related->getVisible() && ! in_array($name, $related->getVisible(), true))) {
                continue;
            }

            $castType = explode(':', $casts[$name] ?? '', 2)[0];

            if (in_array($castType, ['encrypted', 'hashed'], true)
                || is_a($castType, AsEncryptedArrayObject::class, true)
                || is_a($castType, AsEncryptedCollection::class, true)) {
                continue;
            }

            $searchColumns[] = $name;
        }

        $searchColumns = array_values(array_unique($searchColumns));

        if (! $related->newQuery()->applyScopes()->getQuery()->joins) {
            return $searchColumns;
        }

        return array_map(static function (string $column) use ($related, $connection): string {
            $column = $related->qualifyColumn($column);

            // Filament quotes and prefixes PostgreSQL itself; other drivers use raw lower() expressions.
            return $connection->getDriverName() === 'pgsql'
                ? $column
                : $connection->getQueryGrammar()->wrap($column);
        }, $searchColumns);
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
