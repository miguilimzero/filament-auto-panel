<?php

namespace Miguilim\FilamentAutoPanel\Mounters;

use Filament\Resources\Pages\PageRegistration;
use Miguilim\FilamentAutoPanel\Filament\Pages\FilamentAutoResourceCreate;
use Miguilim\FilamentAutoPanel\Filament\Pages\FilamentAutoResourceList;
use Miguilim\FilamentAutoPanel\Filament\Pages\FilamentAutoResourceView;
use Miguilim\FilamentAutoPanel\Filament\Pages\FilamentAutoResourceEdit;

class PageMounter
{
    public static array $mountedClasses = [];

    public static function makeList(string $resource): PageRegistration
    {
        return static::generateAnonymousClass(FilamentAutoResourceList::class, $resource)::route('/');
    }

    public static function makeCreate(string $resource): PageRegistration
    {
        return static::generateAnonymousClass(FilamentAutoResourceCreate::class, $resource)::route('/create');
    }

    public static function makeView(string $resource): PageRegistration
    {
        return static::generateAnonymousClass(FilamentAutoResourceView::class, $resource)::route('/{record}');
    }

    public static function makeEdit(string $resource): PageRegistration
    {
        return static::generateAnonymousClass(FilamentAutoResourceEdit::class, $resource)::route('/{record}/edit');
    }

    public static function makeCustom(string $resource, string $pageClass, string $pageRoute): PageRegistration
    {
        return static::generateAnonymousClass($pageClass, $resource)::route($pageRoute);
    }

    protected static function generateAnonymousClass(string $filamentPage, string $resource): string
    {
        $filamentPageName = array_reverse(explode('\\', $filamentPage))[0];
        $resourceName = array_reverse(explode('\\', $resource))[0];

        $anonymousClass = "{$filamentPageName}{$resourceName}";

        if (! in_array($anonymousClass, static::$mountedClasses)) {
            static::$mountedClasses[] = $anonymousClass;
            eval("class {$anonymousClass} extends {$filamentPage} {protected static string \$resource = '{$resource}';};");
        }

        return $anonymousClass;
    }
}