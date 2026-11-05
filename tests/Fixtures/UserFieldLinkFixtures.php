<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;

require_once __DIR__ . '/UserFieldFixtures.php';

class UserFieldLinkResource extends AutoResource
{
    protected static ?string $model = UserFieldIdentity::class;

    protected static ?string $slug = 'user-field-users';

    public static bool $accessible = true;

    public static function canAccess(): bool
    {
        return static::$accessible;
    }
}

class UserFieldLinkWithoutViewResource extends UserFieldLinkResource
{
    public static function getPages(): array
    {
        return ['index' => PageMounter::makeList(static::class)];
    }
}
