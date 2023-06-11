<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceIndex;

trait HasPageGeneration
{
    public static function makeIndex(string $resource): array
    {
        FilamentAutoResourceIndex::setResource($resource);

        return FilamentAutoResourceIndex::route('/');
    }
}
