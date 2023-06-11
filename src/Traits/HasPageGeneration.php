<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceCreate;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceEdit;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceIndex;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceList;
use Miguilim\FilamentAutoResource\FilamentPages\FilamentAutoResourceView;

trait HasPageGeneration
{
    public static array $createdClasses = [];

    public static function makeIndex(string $resource): array
    {
        return self::generateAnonymousClass(FilamentAutoResourceIndex::class, $resource)::route('/');
    }

    public static function makeList(string $resource): array
    {
        return self::generateAnonymousClass(FilamentAutoResourceList::class, $resource)::route('/');
    }

    public static function makeCreate(string $resource): array
    {
        return self::generateAnonymousClass(FilamentAutoResourceCreate::class, $resource)::route('/create');
    }

    public static function makeView(string $resource): array
    {
        return self::generateAnonymousClass(FilamentAutoResourceView::class, $resource)::route('/{record}');
    }

    public static function makeEdit(string $resource): array
    {
        return self::generateAnonymousClass(FilamentAutoResourceEdit::class, $resource)::route('/{record}/edit');
    }

    protected static function generateAnonymousClass(string $filamentPage, string $resource): string
    {
        $filamentPageName = array_reverse(explode('\\', $filamentPage))[0];
        $resourceName = array_reverse(explode('\\', $resource))[0];

        $anonymousClass = "{$filamentPageName}{$resourceName}";

        if (! in_array($anonymousClass, self::$createdClasses)) {
            self::$createdClasses[] = $anonymousClass;
            eval("class {$anonymousClass} extends {$filamentPage} {protected static string \$resource = '{$resource}';};");
        }

        return $anonymousClass;
    }
}
