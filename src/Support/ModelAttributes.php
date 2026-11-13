<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;

class ModelAttributes
{
    public static function isPublic(Model $model, string $name): bool
    {
        $cast = explode(':', $model->getCasts()[$name] ?? '', 2)[0];

        return ! in_array($name, $model->getHidden(), true)
            && (! $model->getVisible() || in_array($name, $model->getVisible(), true))
            && ! in_array($cast, ['encrypted', 'hashed'], true)
            && ! is_a($cast, AsEncryptedArrayObject::class, true)
            && ! is_a($cast, AsEncryptedCollection::class, true);
    }
}
