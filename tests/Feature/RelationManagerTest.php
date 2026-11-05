<?php

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

it('generates standalone relation manager schemas without the owner foreign key', function (): void {
    $class = RelationManagerMounter::makeStandalone(
        relation: 'products',
        visibleColumns: ['name', 'is_active'],
        associateAttachActions: false,
    );
    $manager = new $class();
    $manager->ownerRecord = Owner::create(['name' => 'Owner']);
    $manager->pageClass = ListProducts::class;

    $form = $manager->form(Schema::make($manager)->model(Product::class));
    $fields = collect($form->getComponents())->keyBy(fn ($field): string => (string) $field->getName());
    $table = $manager->table(Table::make($manager)->relationship(fn () => $manager->getRelationship()));
    $infolist = $manager->infolist(Schema::make($manager)->model(Product::class));
    $entries = collect($infolist->getFlatComponents(withHidden: true))
        ->filter(fn ($component) => $component instanceof TextEntry)
        ->map(fn ($entry) => $entry->getName());

    expect($fields)->not->toHaveKey('owner_id')
        ->and($fields['name'])->toBeInstanceOf(TextInput::class)
        ->and($fields['is_active'])->toBeInstanceOf(Toggle::class)
        ->and($table->getColumns())->toHaveKey('name')->not->toHaveKey('owner.name')
        ->and($entries)->toContain('name')->not->toContain('owner.name');
});

it('generates resource-backed relation manager schemas without DBAL', function (): void {
    $class = RelationManagerMounter::makeFromResource(
        resource: ProductResource::class,
        relation: 'products',
        associateAttachActions: false,
    );
    $manager = new $class();
    $manager->ownerRecord = Owner::create(['name' => 'Owner']);
    $manager->pageClass = ListProducts::class;

    $form = $manager->form(Schema::make($manager)->model(Product::class));
    $fields = collect($form->getComponents())->keyBy(fn ($field): string => (string) $field->getName());
    $table = $manager->table(ProductResource::table(Table::make($manager)->relationship(fn () => $manager->getRelationship())));
    $infolist = $manager->infolist(Schema::make($manager)->model(Product::class));

    expect($fields)->toHaveKey('name')->not->toHaveKey('owner_id')
        ->and($table->getColumns())->toHaveKeys(['name', 'is_active'])->not->toHaveKey('owner.name')
        ->and($infolist->getFlatComponents(withHidden: true))->not->toBeEmpty();
});
