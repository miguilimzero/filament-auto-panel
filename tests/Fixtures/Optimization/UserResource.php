<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization;

use Miguilim\FilamentAutoPanel\AutoResource;

class UserResource extends AutoResource
{
    protected static ?string $model = User::class;

    public static int $pageCalls = 0;

    public static int $relationCalls = 0;

    public static function getPages(): array
    {
        static::$pageCalls++;

        return parent::getPages();
    }

    public static function getRelations(): array
    {
        static::$relationCalls++;

        return parent::getRelations();
    }
}
