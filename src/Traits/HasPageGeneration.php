<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceCreate;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceEdit;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceIndex;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceList;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceView;

trait HasPageGeneration
{
    public static function makeIndex(string $resource): array
    {
        FilamentAutoResourceIndex::setResource($resource);

        return FilamentAutoResourceIndex::route('/');
    }

    public static function makeList(string $resource): array
    {
        FilamentAutoResourceEdit::setResource($resource);

        return FilamentAutoResourceList::route('/');
    }

    public static function makeCreate(string $resource): array
    {
        FilamentAutoResourceEdit::setResource($resource);

        return FilamentAutoResourceCreate::route('/crete');
    }

    public static function makeView(string $resource): array
    {
        FilamentAutoResourceEdit::setResource($resource);

        return FilamentAutoResourceView::route('/{record}');
    }

    public static function makeEdit(string $resource): array
    {
        FilamentAutoResourceEdit::setResource($resource);

        return FilamentAutoResourceEdit::route('/{record}/edit');
    }
}
