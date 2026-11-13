<?php

use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Support\ModelAttributes;

it('uses the same attribute visibility rules for labels searches and filters', function (array $hidden, array $visible, bool $expected): void {
    $model = new class extends Model {};
    $model->setHidden($hidden)->setVisible($visible);

    expect(ModelAttributes::isPublic($model, 'email'))->toBe($expected);
})->with([
    'default visibility' => [[], [], true],
    'hidden attribute' => [['email'], [], false],
    'another hidden attribute' => [['password'], [], true],
    'explicitly visible' => [[], ['name', 'email'], true],
    'excluded from visible list' => [[], ['name'], false],
    'hidden overrides visible' => [['email'], ['email'], false],
]);

it('excludes sensitive casts without excluding ordinary scalar casts', function (string $cast, bool $expected): void {
    $model = new class extends Model {};
    $model->mergeCasts(['email' => $cast]);

    expect(ModelAttributes::isPublic($model, 'email'))->toBe($expected);
})->with([
    'string' => ['string', true],
    'integer' => ['integer', true],
    'decimal' => ['decimal:10', true],
    'hashed' => ['hashed', false],
    'encrypted' => ['encrypted', false],
    'encrypted array' => ['encrypted:array', false],
    'encrypted object' => ['encrypted:object', false],
    'encrypted collection' => ['encrypted:collection', false],
    'encrypted array object class' => [AsEncryptedArrayObject::class, false],
    'encrypted array object parameters' => [AsEncryptedArrayObject::class . ':custom', false],
    'encrypted collection class' => [AsEncryptedCollection::class, false],
    'encrypted collection parameters' => [AsEncryptedCollection::class . ':custom', false],
]);
