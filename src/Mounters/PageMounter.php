<?php

namespace Miguilim\FilamentAutoPanel\Mounters;

use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceListRecords;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceViewRecord;


class PageMounter
{
    public static array $mountedClasses = [];

    public static function makeList(string $resource): PageRegistration
    {
        return static::generateAnonymousClass(AutoResourceListRecords::class, $resource)::route('/');
    }

    public static function makeView(string $resource): PageRegistration
    {
        return static::generateAnonymousClass(AutoResourceViewRecord::class, $resource)::route('/{record}');
    }

    public static function makeCustom(string $resource, string $pageClass, string $pageRoute): PageRegistration
    {
        return static::generateAnonymousClass($pageClass, $resource)::route($pageRoute);
    }

    /**

     * @return class-string<Page>
     */
    protected static function generateAnonymousClass(string $filamentPage, string $resource): string
    {
        $filamentPage = RuntimeClassFactory::resolveClass($filamentPage, Page::class);
        $resource = RuntimeClassFactory::resolveClass($resource, Resource::class);

        $class = RuntimeClassFactory::make($filamentPage, [
            'protected static string $resource' => $resource,
        ]);

        if (! in_array($class, static::$mountedClasses, true)) {
            static::$mountedClasses[] = $class;
        }

        return $class;
    }
}
