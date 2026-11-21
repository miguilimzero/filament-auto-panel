<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\AutoResource;

class ModelAttributes
{
    public static function isPublic(Model $model, string $name): bool
    {
        return ! in_array($name, $model->getHidden(), true)
            && (! $model->getVisible() || in_array($name, $model->getVisible(), true))
            && ! static::isHashed($model, $name)
            && ! static::isEncrypted($model, $name)
            && ! in_array($name, static::excludedColumns($model), true);
    }

    /**
     * Columns excluded by the current panel's Auto Resources for the given model.
     *
     * @return list<string>
     */
    public static function excludedColumns(Model $model): array
    {
        $columns = [];

        foreach (Filament::getCurrentPanel()?->getResources() ?? [] as $resource) {
            if (is_a($resource, AutoResource::class, true) && $model instanceof ($resource::getModel())) {
                $columns = [...$columns, ...$resource::getExcludedColumns()];
            }
        }

        return array_values(array_unique($columns));
    }

    public static function isHashed(Model $model, string $name): bool
    {
        return static::isHashedCast($model->getCasts()[$name] ?? null);
    }

    public static function isEncrypted(Model $model, string $name): bool
    {
        return static::isEncryptedCast($model->getCasts()[$name] ?? null);
    }

    public static function isHashedCast(?string $cast): bool
    {
        return $cast === 'hashed';
    }

    /** Covers `encrypted`, `encrypted:*`, `AsEncryptedArrayObject` and `AsEncryptedCollection`. */
    public static function isEncryptedCast(?string $cast): bool
    {
        $cast = explode(':', $cast ?? '', 2)[0];

        return $cast === 'encrypted'
            || is_a($cast, AsEncryptedArrayObject::class, true)
            || is_a($cast, AsEncryptedCollection::class, true);
    }
}
