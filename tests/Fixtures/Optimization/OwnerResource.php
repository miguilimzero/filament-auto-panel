<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization;

use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

class OwnerResource extends AutoResource
{
    protected static ?string $model = Owner::class;

    public static int $pageCalls = 0;

    public static int $relationCalls = 0;

    public static function getPages(): array
    {
        static::$pageCalls++;

        return parent::getPages();
    }

    public static function getExtraPages(): array
    {
        return [
            'custom' => PageMounter::makeCustom(static::class, CustomOwners::class, '/custom'),
        ];
    }

    public static function getRelations(): array
    {
        static::$relationCalls++;

        return [
            new RelationManagerConfiguration(
                RelationManagerMounter::makeFromResource(ProductResource::class, 'products', false),
                ['lazy' => false],
            ),
            RelationGroup::make('Standalone', [
                new RelationManagerConfiguration(RelationManagerMounter::makeStandalone(
                    relation: 'products',
                    visibleColumns: ['name', 'status'],
                    searchableColumns: ['name' => 'both'],
                    enumDictionary: ['status' => ['draft' => ["Owner's \\ draft", 'gray']]],
                    recordTitleAttribute: 'name',
                    associateAttachActions: false,
                    intrusive: false,
                    readOnly: true,
                )),
            ]),
        ];
    }
}
