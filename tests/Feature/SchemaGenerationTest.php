<?php

use Composer\InstalledVersions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\SecondaryProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SchemaReader;

it('reads real native column metadata with DBAL absent', function (): void {
    expect(InstalledVersions::isInstalled('doctrine/dbal'))->toBeFalse();

    $columns = collect(Schema::getColumns('products'))->keyBy('name');
    $reader = new SchemaReader(Product::class);

    expect($reader->normalize($columns['is_active'])->getType())->toBe('boolean')
        ->and($reader->normalize($columns['quantity'])->getType())->toBe('integer')
        ->and($reader->normalize($columns['name'])->getLength())->toBe(DB::getDriverName() === 'sqlite' ? null : 80)
        ->and($reader->normalize($columns['description'])->getNotNull())->toBeFalse();

    if (DB::getDriverName() !== 'sqlite') {
        expect($reader->normalize($columns['price'])->getDecimalPlaces())->toBe(2);
    }
});

it('generates form fields from the current database schema', function (): void {
    $fields = FormGenerator::make(Product::class, relationManagerView: true);

    expect($fields['id'])->toBeInstanceOf(TextInput::class)
        ->and($fields['id']->isDisabled())->toBeTrue()
        ->and($fields['name'])->toBeInstanceOf(TextInput::class)
        ->and($fields['name']->getMaxLength())->toBe(DB::getDriverName() === 'sqlite' ? null : 80)
        ->and($fields['name']->isRequired())->toBeTrue()
        ->and($fields['description'])->toBeInstanceOf(Textarea::class)
        ->and($fields['description']->isRequired())->toBeFalse()
        ->and($fields['is_active'])->toBeInstanceOf(Toggle::class)
        ->and($fields['quantity'])->toBeInstanceOf(TextInput::class)
        ->and($fields['quantity']->isNumeric())->toBeTrue()
        ->and($fields['price']->isNumeric())->toBeTrue()
        ->and($fields['published_on'])->toBeInstanceOf(DatePicker::class)
        ->and($fields['published_at'])->toBeInstanceOf(DateTimePicker::class)
        ->and($fields['created_at']->isDisabled())->toBeTrue();

    expect($fields['metadata'])->toBeInstanceOf(KeyValue::class)
        ->and($fields['metadata']->getColumnSpan('default'))->toBe('full');
});

it('generates table columns with existing display and search settings', function (): void {
    $columns = TableGenerator::make(
        Product::class,
        visibleColumns: ['name'],
        searchableColumns: ['name' => 'both'],
    );

    expect($columns['id'])->toBeInstanceOf(TextColumn::class)
        ->and($columns['id']->isSearchable())->toBeTrue()
        ->and($columns['is_active'])->toBeInstanceOf(IconColumn::class)
        ->and($columns['quantity']->isSortable())->toBeTrue()
        ->and($columns['name']->isGloballySearchable())->toBeTrue()
        ->and($columns['name']->isIndividuallySearchable())->toBeTrue()
        ->and($columns['name']->isToggledHiddenByDefault())->toBeFalse()
        ->and($columns['description']->isToggledHiddenByDefault())->toBeTrue();
});

it('generates infolist entries and a separate timestamps section', function (): void {
    $schema = FilamentSchema::make(new ListProducts())->model(Product::class)->components(InfolistGenerator::make(Product::class));
    $entries = collect($schema->getFlatComponents(withHidden: true))
        ->filter(fn ($component) => $component instanceof TextEntry || $component instanceof IconEntry)
        ->keyBy(fn ($entry): string => (string) $entry->getName());

    expect($entries['is_active'])->toBeInstanceOf(IconEntry::class)
        ->and($entries['name'])->toBeInstanceOf(TextEntry::class)
        ->and($entries)->toHaveKeys(['created_at', 'updated_at', 'deleted_at']);
});

it('preserves exclusions overwrites and enum dictionaries', function (): void {
    $overwrite = TextInput::make('name')->label('Custom name');
    $fields = FormGenerator::make(
        Product::class,
        exceptColumns: ['description'],
        overwriteColumns: ['name' => $overwrite],
        enumDictionary: ['status' => ['draft' => 'Draft', 'published' => 'Published']],
        relationManagerView: true,
    );

    expect($fields)->not->toHaveKey('description')
        ->and($fields['name'])->toBe($overwrite)
        ->and($fields['status'])->toBeInstanceOf(Select::class)
        ->and($fields['status']->getOptions())->toBe(['draft' => 'Draft', 'published' => 'Published']);
});

it('guesses belongs-to relationships using native schema metadata', function (): void {
    expect(RelationshipGuesser::guessBelongsTo('owner_id', new Product()))->toBe(['owner', 'name']);

    $fields = FormGenerator::make(Product::class, relationManagerView: true);
    $columns = TableGenerator::make(Product::class);

    expect($fields['owner_id'])->toBeInstanceOf(Select::class)
        ->and($fields['owner_id']->getRelationshipName())->toBe('owner')
        ->and($columns['owner_id']->getName())->toBe('owner.name');
});

it('falls back to a relationship title column', function (): void {
    Schema::table('owners', fn (Blueprint $table) => $table->renameColumn('name', 'title'));

    expect(RelationshipGuesser::guessBelongsTo('owner_id', new Product()))->toBe(['owner', 'title']);
});

it('falls back to the native primary index for a relationship title', function (): void {
    Schema::table('owners', function (Blueprint $table): void {
        $table->renameColumn('id', 'owner_key');
        $table->dropColumn('name');
    });

    expect(RelationshipGuesser::guessBelongsTo('owner_id', new Product()))->toBe(['owner', 'owner_key']);
});

it('combines morph displays and generates a native morph selector in forms', function (): void {
    $columns = TableGenerator::make(Product::class);
    $fields = FormGenerator::make(Product::class, relationManagerView: true);

    expect($columns)->toHaveKey('subject')->not->toHaveKeys(['subject_type', 'subject_id'])
        ->and($columns['subject']->getName())->toBe('subject.name')
        ->and($fields)->toHaveKey('subject')->not->toHaveKeys(['subject_type', 'subject_id'])
        ->and($fields['subject'])->toBeInstanceOf(MorphToSelect::class);
});

it('uses the model connection and table prefix rather than the default connection', function (): void {
    Schema::connection('secondary')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('connection_label', 25);
    });

    $fields = FormGenerator::make(SecondaryProduct::class, relationManagerView: true);

    expect(array_keys($fields))->toBe(['id', 'connection_label'])
        ->and($fields['connection_label']->getMaxLength())->toBe(DB::getDriverName() === 'sqlite' ? null : 25);
});

it('mounts resource forms for both create and edit operations', function (string $operation): void {
    $schema = ProductResource::form(FilamentSchema::make(new ListProducts())->model(Product::class)->operation($operation));
    $groups = $schema->getComponents();
    $fields = collect($groups[0]->getChildSchema()->getComponents())->keyBy(fn ($field): string => (string) $field->getName());

    expect($groups)->toHaveCount(1)
        ->and($fields['name'])->toBeInstanceOf(TextInput::class)
        ->and($fields['is_active'])->toBeInstanceOf(Toggle::class);
})->with(['create', 'edit']);

it('mounts resource tables and infolists without DBAL', function (): void {
    $table = ProductResource::table(Table::make(new ListProducts()));
    $infolist = ProductResource::infolist(FilamentSchema::make(new ListProducts())->model(Product::class));

    expect($table->getColumns())->toHaveKeys(['id', 'name', 'owner.name', 'is_active'])
        ->and($table->getDefaultSortColumn())->toBe('id')
        ->and($table->getFilters())->toHaveKey('trashed')
        ->and($infolist->getFlatComponents(withHidden: true))->not->toBeEmpty();
});
