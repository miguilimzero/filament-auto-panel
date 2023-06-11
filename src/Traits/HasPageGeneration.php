<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Miguilim\FilamentAutoResource\Filament\Pages\FilamentAutoResourceCreate;
use Miguilim\FilamentAutoResource\Filament\Pages\FilamentAutoResourceEdit;
use Miguilim\FilamentAutoResource\Filament\Pages\FilamentAutoResourceList;
use Miguilim\FilamentAutoResource\Filament\Pages\FilamentAutoResourceView;

trait HasPageGeneration
{
    public static array $createdClasses = [];

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
