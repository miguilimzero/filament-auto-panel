<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;

require_once __DIR__ . '/Issue17UserFieldFixtures.php';

class Issue17UserLinkResource extends AutoResource
{
    protected static ?string $model = Issue17Identity::class;

    protected static ?string $slug = 'issue17-users';

    public static bool $accessible = true;

    public static function canAccess(): bool
    {
        return static::$accessible;
    }
}

class Issue17UserLinkWithoutViewResource extends Issue17UserLinkResource
{
    public static function getPages(): array
    {
        return ['index' => PageMounter::makeList(static::class)];
    }
}
