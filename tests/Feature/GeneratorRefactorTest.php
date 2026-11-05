<?php

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Support\Components\ViewComponent;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\SecondaryProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SchemaReader;

function generatorRefactorMountSchema(array $components, string $model = Product::class): void
{
    FilamentSchema::make(new ListProducts())->model($model)->components($components)->getFlatComponents(withHidden: true);
}

class GeneratorRefactorCastedProduct extends Product
{
    protected $table = 'products';

    protected function casts(): array
    {
        return [
            'quantity' => 'boolean',
            'description' => 'array',
            'name' => 'integer',
            'is_active' => 'string',
            'status' => 'immutable_date:Y-m-d',
            'price' => 'decimal:3',
        ];
    }
}

class GeneratorRefactorCustomTimestampProduct extends Product
{
    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'modified_at';

    public const DELETED_AT = 'removed_at';

    protected $table = 'products';
}

class GeneratorRefactorNoTimestampProduct extends Model
{
    public $timestamps = false;

    protected $table = 'products';
}

class GeneratorRefactorMorphProduct extends Product
{
    protected $table = 'generator_morph_products';

    public function reviewTarget(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewOwner()
    {
        return $this->belongsTo(Owner::class, 'review_owner_id');
    }
}

class GeneratorRefactorUnrelatedProduct extends Model
{
    protected $table = 'generator_morph_products';

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'different_owner_id');
    }

    public function subject()
    {
        return $this->hasMany(Owner::class);
    }

    public function reviewTarget()
    {
        return 'not a relationship';
    }

    public function orphan(string $required)
    {
        throw new LogicException('Methods requiring arguments must not be called.');
    }
}

class GeneratorRefactorSwitchingProduct extends Product
{
    protected $table = 'products';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection(config('generator-refactor.connection', 'testing'));
    }
}

class GeneratorRefactorFormHost extends ListProducts
{
    public array $generatorData = [];

    public function setGeneratorTable(Table $table): void
    {
        $this->table = $table;
    }
}

class GeneratorRefactorJsonInfolistGenerator extends InfolistGenerator
{
    public function jsonEntry(): ViewComponent
    {
        return $this->handleArrayColumn(new Column([
            'name' => 'metadata',
            'type_name' => 'json',
            'type' => 'json',
            'nullable' => true,
        ]));
    }
}

class GeneratorRefactorTitleTarget extends Model
{
    public $timestamps = false;

    protected $table = 'generator_morph_titles';

    protected $guarded = [];
}

class GeneratorRefactorKeyTarget extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'target_key';

    protected $table = 'generator_morph_keys';

    protected $guarded = [];
}

it('creates fresh generated fields and display components on every call', function (): void {
    $firstForm = FormGenerator::make(Product::class, relationManagerView: true);
    $firstTable = TableGenerator::make(Product::class);
    $firstInfolist = InfolistGenerator::make(Product::class);
    $firstEntries = $firstInfolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();

    $firstForm['name']->label('Polluted form');
    $firstTable['name']->label('Polluted table');
    $firstEntries['name']->label('Polluted infolist');

    $nextForm = FormGenerator::make(Product::class, relationManagerView: true);
    $nextTable = TableGenerator::make(Product::class);
    $nextInfolist = InfolistGenerator::make(Product::class);
    $nextEntries = $nextInfolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();

    expect($nextForm['name'])->not->toBe($firstForm['name'])
        ->and($nextForm['name']->getLabel())->toBe('Name')
        ->and($nextTable['name'])->not->toBe($firstTable['name'])
        ->and($nextTable['name']->getLabel())->toBe('Name')
        ->and($nextInfolist[0])->not->toBe($firstInfolist[0])
        ->and($nextEntries['name'])->not->toBe($firstEntries['name'])
        ->and($nextEntries['name']->getLabel())->toBe('Name');
});

it('retains explicit overrides without serializing or sharing closure configuration', function (): void {
    $first = TextInput::make('name')->label(fn () => 'First');
    $second = TextInput::make('name')->label(fn () => 'Second');
    $firstFields = FormGenerator::make(Product::class, overwriteColumns: ['name' => $first], relationManagerView: true);
    $secondFields = FormGenerator::make(Product::class, overwriteColumns: ['name' => $second], relationManagerView: true);
    $thirdFields = FormGenerator::make(Product::class, overwriteColumns: ['name' => $first], relationManagerView: true);

    expect($firstFields['name'])->toBe($first)
        ->and($secondFields['name'])->toBe($second)
        ->and($secondFields['name']->getLabel())->toBe('Second')
        ->and($thirdFields['name'])->toBe($first)
        ->and($thirdFields['status'])->not->toBe($firstFields['status']);
});

it('preserves exclusion overwrite enum and relationship priorities and output keys', function (): void {
    $override = TextInput::make('owner_id');
    $fields = FormGenerator::make(
        Product::class,
        exceptColumns: ['description'],
        overwriteColumns: ['description' => TextInput::make('description'), 'owner_id' => $override],
        enumDictionary: ['description' => [], 'owner_id' => [1 => 'Owner'], 'subject_id' => [1 => 'Subject']],
        relationManagerView: true,
    );

    expect($fields)->not->toHaveKey('description')
        ->and($fields['owner_id'])->toBe($override)
        ->and($fields['subject_id'])->toBeInstanceOf(Select::class)
        ->and($fields['subject_id']->getOptions())->toBe([1 => 'Subject'])
        ->and(array_keys($fields))->toBe(array_values(array_diff(Schema::getColumnListing('products'), ['description'])));
});

it('honors model casts when constructing and dispatching normalized columns', function (): void {
    $fields = FormGenerator::make(GeneratorRefactorCastedProduct::class, relationManagerView: true);
    $reader = new SchemaReader(GeneratorRefactorCastedProduct::class);
    $columns = collect(Schema::getColumns('products'))->keyBy('name');

    expect($fields['quantity'])->toBeInstanceOf(Toggle::class)
        ->and($fields['description'])->toBeInstanceOf(KeyValue::class)
        ->and($fields['name']->isNumeric())->toBeTrue()
        ->and($fields['is_active'])->toBeInstanceOf(TextInput::class)
        ->and($fields['is_active']->isNumeric())->toBeFalse()
        ->and($fields['status'])->toBeInstanceOf(DatePicker::class)
        ->and($reader->normalize($columns['price'])->getDecimalPlaces())->toBe(3)
        ->and(FormGenerator::make(Product::class, relationManagerView: true)['metadata'])->toBeInstanceOf(KeyValue::class);
});

it('round trips array and collection casts through full-width key-value fields', function (string $cast, array $payload): void {
    $record = (new Product())->mergeCasts(['metadata' => $cast]);
    $record->forceFill(['name' => 'Key-value data', 'price' => '12.34', 'metadata' => $payload])->save();
    $fields = FormGenerator::makeForModel($record, relationManagerView: true);
    $host = new GeneratorRefactorFormHost();
    $schema = FilamentSchema::make($host)->model($record)->operation('edit')->statePath('generatorData')->components([$fields['metadata']]);
    $schema->fill($record->attributesToArray());

    expect($fields['metadata'])->toBeInstanceOf(KeyValue::class)
        ->and($fields['metadata']->getColumnSpan('default'))->toBe('full')
        ->and($schema->getState()['metadata'])->toBe($payload);

    $host->generatorData['metadata'][] = ['key' => 'extra.key', 'value' => 'Edited value'];
    $payload['extra.key'] = 'Edited value';
    $record->forceFill($schema->getState())->save();

    expect($record->refresh()->attributesToArray()['metadata'])->toEqual($payload);
})->with(['array', 'collection'])->with([
    'associative keys' => [['color' => 'blue', 'dotted.key' => 'Literal key', '01' => 'Leading zero']],
    'numeric list keys' => [['First', 'Second']],
]);

it('shows array object and collection data as full-width key-value entries', function (string $cast): void {
    $payload = ['color' => 'blue', 'nested' => ['enabled' => true]];
    $record = (new Product())->mergeCasts(['metadata' => $cast]);
    $record->forceFill(['metadata' => $payload]);
    $schema = FilamentSchema::make(new ListProducts())->model($record)->components(InfolistGenerator::makeForModel($record));
    $entry = collect($schema->getFlatComponents(withHidden: true))->first(fn ($component) => $component instanceof KeyValueEntry && $component->getName() === 'metadata');

    expect($entry)->toBeInstanceOf(KeyValueEntry::class)
        ->and($entry->getColumnSpan('default'))->toBe('full')
        ->and($entry->toEmbeddedHtml())->toContain('color', 'blue', 'nested', e('{"enabled":true}'));
})->with(['array', 'object', 'collection']);

it('decodes native JSON values for key-value entries without a model cast', function (mixed $payload): void {
    $generator = new GeneratorRefactorJsonInfolistGenerator(GeneratorRefactorNoTimestampProduct::class);
    $record = (new GeneratorRefactorNoTimestampProduct())->setRawAttributes([
        'metadata' => json_encode($payload, JSON_THROW_ON_ERROR),
    ]);
    $entry = $generator->jsonEntry();
    FilamentSchema::make(new ListProducts())->model($record)->components([$entry])->getComponents();

    expect($entry)->toBeInstanceOf(KeyValueEntry::class)
        ->and($entry->getState())->toBe($payload === null ? null : (array) $payload)
        ->and($entry->toEmbeddedHtml())->toContain('fi-in-key-value');
})->with([
    'object' => [['color' => 'blue', 'nested' => ['enabled' => true]]],
    'list' => [['First', 'Second']],
    'scalar' => [42],
    'null' => [null],
]);

it('preserves floating point fractions and applies the model decimal scale', function (): void {
    $columns = TableGenerator::make(Product::class);
    Table::make(new ListProducts())->columns($columns);
    $infolist = InfolistGenerator::make(Product::class);
    generatorRefactorMountSchema($infolist);
    $entries = $infolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();

    expect($columns['price']->formatState('1234.5'))->toBe('1,234.50')
        ->and($entries['price']->formatState('1234.5'))->toBe('1,234.50');

    Schema::table('products', fn (Blueprint $table) => $table->double('floating_value')->nullable());
    $columns = TableGenerator::make(Product::class);
    Table::make(new ListProducts())->columns($columns);

    expect($columns['floating_value']->formatState(1234.56))->toBe('1,234.56');
});

it('leaves unknown and unbounded lengths unset rather than forbidding input', function (): void {
    $fields = FormGenerator::make(Product::class, relationManagerView: true);

    expect($fields['name']->getMaxLength())->toBe(DB::getDriverName() === 'sqlite' ? null : 80)
        ->and($fields['description']->getMaxLength())->toBe(DB::getDriverName() === 'sqlite' ? null : 65_535)
        ->and($fields['quantity']->getMaxLength())->toBeNull();
});

it('falls back safely for unknown and incomplete enum dictionary entries', function (string $state, string $label, string $color): void {
    $dictionary = ['status' => ['draft' => ['Draft', 'warning'], 'plain' => 'Plain', 'empty' => [], 'label_only' => ['Label only']]];
    $column = TableGenerator::make(Product::class, enumDictionary: $dictionary)['status'];
    $infolist = InfolistGenerator::make(Product::class, enumDictionary: $dictionary);
    generatorRefactorMountSchema($infolist);
    $entry = $infolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents()['status'];

    expect($column->formatState($state))->toBe($label)
        ->and($column->getColor($state))->toBe($color)
        ->and($entry->formatState($state))->toBe($label)
        ->and($entry->getColor($state))->toBe($color);
})->with([
    ['unknown', 'unknown', 'primary'],
    ['draft', 'Draft', 'warning'],
    ['plain', 'Plain', 'primary'],
    ['empty', 'empty', 'primary'],
    ['label_only', 'Label only', 'primary'],
]);

it('handles incomplete enum option labels without undefined offsets', function (): void {
    $fields = FormGenerator::make(Product::class, enumDictionary: ['status' => ['draft' => [], 'live' => ['Live', 'success']]], relationManagerView: true);

    expect($fields['status']->getOptions())->toBe(['draft' => 'draft', 'live' => 'Live']);
});

it('pairs multiple morphs by prefix regardless of column order and leaves unrelated columns alone', function (): void {
    Schema::create('generator_morph_products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->string('unrelated_type')->nullable();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('name');
        $table->string('review_target_type')->nullable();
        $table->string('subject_type')->nullable();
        $table->unsignedBigInteger('review_target_id')->nullable();
        $table->unsignedBigInteger('review_owner_id')->nullable();
        $table->unsignedBigInteger('orphan_id')->nullable();
        $table->string('orphan_type')->nullable();
        $table->unsignedBigInteger('save_id')->nullable();
    });

    try {
        $columns = TableGenerator::make(GeneratorRefactorMorphProduct::class);
        $fields = FormGenerator::make(GeneratorRefactorMorphProduct::class, relationManagerView: true);
        $infolist = InfolistGenerator::make(GeneratorRefactorMorphProduct::class);
        $entries = $infolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();

        expect($columns)->toHaveKeys(['subject', 'reviewTarget', 'owner_id', 'unrelated_type', 'orphan_id', 'orphan_type'])
            ->not->toHaveKeys(['subject_id', 'subject_type', 'review_target_id', 'review_target_type'])
            ->and($columns['subject']->getName())->toBe('subject.name')
            ->and($columns['reviewTarget']->getName())->toBe('reviewTarget.name')
            ->and($columns['owner_id']->getName())->toBe('owner.name')
            ->and($columns['review_owner_id']->getName())->toBe('reviewOwner.name')
            ->and($entries)->toHaveKeys(['subject', 'reviewTarget', 'orphan_id', 'orphan_type'])
            ->and($infolist)->toHaveCount(1)
            ->and($fields)->toHaveKeys(['subject', 'reviewTarget'])
            ->not->toHaveKeys(['subject_id', 'subject_type', 'review_target_id', 'review_target_type'])
            ->and($fields['subject'])->toBeInstanceOf(MorphToSelect::class)
            ->and($fields['reviewTarget'])->toBeInstanceOf(MorphToSelect::class);

        $unrelated = TableGenerator::make(GeneratorRefactorUnrelatedProduct::class);

        expect($unrelated)->toHaveKeys(['subject_id', 'subject_type', 'review_target_id', 'review_target_type'])
            ->not->toHaveKeys(['subject', 'reviewTarget'])
            ->and($unrelated['owner_id']->getName())->toBe('owner_id')
            ->and($unrelated['save_id']->getName())->toBe('save_id')
            ->and(DB::table('generator_morph_products')->count())->toBe(0);
    } finally {
        Schema::dropIfExists('generator_morph_products');
    }
});

it('does not combine a declared morph relationship when one of its columns is missing', function (): void {
    Schema::table('products', fn (Blueprint $table) => $table->dropIndex(['subject_type', 'subject_id']));
    Schema::table('products', fn (Blueprint $table) => $table->dropColumn('subject_id'));

    $columns = TableGenerator::make(Product::class);

    expect($columns)->toHaveKey('subject_type')->not->toHaveKey('subject');
});

it('uses the related model connection including the inherited connection and table prefix', function (): void {
    Schema::connection('secondary')->create('owners', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });
    Schema::connection('secondary')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('connection_label');
    });

    try {
        expect(RelationshipGuesser::guessBelongsTo('owner_id', new SecondaryProduct()))->toBe(['owner', 'title'])
            ->and(TableGenerator::make(SecondaryProduct::class)['owner_id']->getName())->toBe('owner.title');

        config(['generator-refactor.connection' => 'testing']);
        $first = FormGenerator::make(GeneratorRefactorSwitchingProduct::class, relationManagerView: true);
        config(['generator-refactor.connection' => 'secondary']);
        $second = FormGenerator::make(GeneratorRefactorSwitchingProduct::class, relationManagerView: true);

        expect($first)->toHaveKey('name')->not->toHaveKey('connection_label')
            ->and($second)->toHaveKey('connection_label')->not->toHaveKey('name');
    } finally {
        Schema::connection('secondary')->dropIfExists('owners');
    }
});

it('uses actual custom timestamp names without duplicating them or disabling unrelated date fields', function (): void {
    Schema::table('products', function (Blueprint $table): void {
        $table->renameColumn('created_at', 'inserted_at');
        $table->renameColumn('updated_at', 'modified_at');
        $table->renameColumn('deleted_at', 'removed_at');
        $table->dateTime('created_at')->nullable();
    });

    $fields = FormGenerator::make(GeneratorRefactorCustomTimestampProduct::class, relationManagerView: true);
    generatorRefactorMountSchema($fields, GeneratorRefactorCustomTimestampProduct::class);
    $infolist = InfolistGenerator::make(GeneratorRefactorCustomTimestampProduct::class);
    $entries = $infolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();
    $timestamps = $infolist[1]->getDefaultChildComponents();

    expect($fields['inserted_at']->isDisabled())->toBeTrue()
        ->and($fields['modified_at']->isDisabled())->toBeTrue()
        ->and($fields['removed_at']->isDisabled())->toBeTrue()
        ->and($fields['created_at']->isDisabled())->toBeFalse()
        ->and($entries)->toHaveKey('created_at')->not->toHaveKeys(['inserted_at', 'modified_at', 'removed_at'])
        ->and(array_map(fn ($entry) => $entry->getName(), $timestamps))->toBe(['inserted_at', 'modified_at', 'removed_at']);
});

it('honors timestamp exclusions overwrites and enum priorities in infolists', function (): void {
    $override = TextEntry::make('created_at')->label('Custom timestamp');
    $infolist = InfolistGenerator::make(
        Product::class,
        exceptColumns: ['updated_at'],
        overwriteColumns: ['created_at' => $override],
        enumDictionary: ['created_at' => ['now' => 'Ignored'], 'deleted_at' => ['never' => 'Never']],
    );
    generatorRefactorMountSchema($infolist);
    $timestamps = $infolist[1]->getDefaultChildComponents();

    expect($timestamps)->toHaveCount(2)
        ->and($timestamps[0])->toBe($override)
        ->and($timestamps[1]->formatState('never'))->toBe('Never');

    $withoutTimestamps = InfolistGenerator::make(Product::class, exceptColumns: ['created_at', 'updated_at', 'deleted_at']);

    expect($withoutTimestamps)->toHaveCount(1);
});

it('keeps conventional date columns ordinary when the model does not use timestamps or soft deletes', function (): void {
    $fields = FormGenerator::make(GeneratorRefactorNoTimestampProduct::class, relationManagerView: true);
    generatorRefactorMountSchema($fields, GeneratorRefactorNoTimestampProduct::class);
    $infolist = InfolistGenerator::make(GeneratorRefactorNoTimestampProduct::class);
    $entries = $infolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();

    expect($fields['created_at']->isDisabled())->toBeFalse()
        ->and($fields['updated_at']->isDisabled())->toBeFalse()
        ->and($fields['deleted_at']->isDisabled())->toBeFalse()
        ->and($infolist)->toHaveCount(1)
        ->and($entries)->toHaveKeys(['created_at', 'updated_at', 'deleted_at']);
});

it('round trips populated object casts through JSON form hydration validation and persistence', function (string $cast): void {
    $payload = json_decode('{"01":"leading zero","dotted.key":{"nested":[1,true,null,{"empty":{}}]},"empty":{},"list":[],"decimal":1.0}', flags: JSON_THROW_ON_ERROR);
    $record = (new Product())->mergeCasts(['description' => $cast]);
    $record->forceFill(['name' => 'Object cast', 'price' => '12.34', 'description' => $payload])->save();
    $record->refresh();
    $fields = FormGenerator::makeForModel(model: $record, relationManagerView: true);
    $host = new GeneratorRefactorFormHost();
    $schema = FilamentSchema::make($host)->model($record)->operation('edit')->statePath('generatorData')->components([$fields['description']]);
    $schema->fill($record->attributesToArray());

    expect($fields['description'])->toBeInstanceOf(Textarea::class)
        ->and($fields['metadata'])->toBeInstanceOf(KeyValue::class)
        ->and($host->generatorData['description'])->toBeString()
        ->and(json_decode($host->generatorData['description'], flags: JSON_THROW_ON_ERROR))->toEqual($payload);

    $state = $schema->getState();

    expect($state['description'])->toBeInstanceOf(stdClass::class)
        ->and($state['description'])->toEqual($payload)
        ->and($state['description']->empty)->toBeInstanceOf(stdClass::class)
        ->and($state['description']->list)->toBe([]);

    $record->forceFill($state)->save();
    expect($record->refresh()->description)->toEqual($payload);

    $payload->{'dotted.key'}->nested[] = (object) ['added' => 'Olá / JSON'];
    $host->generatorData['description'] = json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $record->forceFill($schema->getState())->save();

    expect($record->refresh()->description)->toEqual($payload)
        ->and($record->description->{'01'})->toBe('leading zero');
})->with(['object', 'encrypted:object']);

it('round trips object-cast values through the generated resource operation groups', function (string $operation, mixed $payload): void {
    $record = (new Product())->mergeCasts(['description' => 'object']);
    $record->forceFill(['description' => $payload]);
    $excluded = array_values(array_diff(Schema::getColumnListing('products'), ['description']));
    $host = new GeneratorRefactorFormHost();
    $schema = FilamentSchema::make($host)->model($record)->operation($operation)->statePath('generatorData')
        ->components(FormGenerator::makeForModel($record, exceptColumns: $excluded));
    $schema->fill($record->attributesToArray());

    expect(json_decode($host->generatorData['description'], flags: JSON_THROW_ON_ERROR))->toEqual($payload)
        ->and($schema->getState()['description'])->toEqual($payload);
})->with(['create', 'edit'])->with([
    'nested object' => [(object) ['nested' => (object) ['keep' => [1, true, null]], 'empty' => (object) []]],
    'JSON-looking string' => ['{"keep":"as a string"}'],
    'numeric string' => ['123'],

    'boolean' => [false],
]);

it('rejects invalid JSON before an object field can overwrite persisted data', function (): void {
    $record = (new Product())->mergeCasts(['description' => 'object']);
    $record->forceFill(['name' => 'Keep object', 'price' => '1.00', 'description' => (object) ['keep' => true]])->save();
    $field = FormGenerator::makeForModel($record, relationManagerView: true)['description'];
    $host = new GeneratorRefactorFormHost();
    $schema = FilamentSchema::make($host)->model($record)->statePath('generatorData')->components([$field]);
    $schema->fill($record->attributesToArray());
    $host->generatorData['description'] = '{"invalid":';

    expect(fn () => $schema->getState())->toThrow(ValidationException::class)
        ->and($record->refresh()->description)->toEqual((object) ['keep' => true]);
});

it('preserves empty objects and null when editing nullable object fields', function (?object $payload): void {
    $record = (new Product())->mergeCasts(['description' => 'object']);
    $record->forceFill(['name' => 'Empty object', 'price' => '1.00', 'description' => $payload])->save();
    $field = FormGenerator::makeForModel($record, relationManagerView: true)['description'];
    $host = new GeneratorRefactorFormHost();
    $schema = FilamentSchema::make($host)->model($record)->statePath('generatorData')->components([$field]);
    $schema->fill($record->attributesToArray());
    $state = $schema->getState();

    expect($state['description'])->toEqual($payload);

    $record->forceFill($state)->save();
    expect($record->refresh()->description)->toEqual($payload);
})->with([[(object) []], [null]]);

it('generates all schemas from an actual related model with an inherited owner connection', function (): void {
    Schema::connection('secondary')->create('owners', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });
    Schema::connection('secondary')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('connection_label');
        $table->unsignedInteger('quantity')->default(0);
        $table->string('status');
    });

    try {
        $owner = (new Owner())->setConnection('secondary');
        $owner->forceFill(['title' => 'Secondary owner'])->save();
        $model = $owner->products()->getRelated()->mergeCasts(['connection_label' => 'boolean']);
        $formOverride = TextInput::make('quantity')->label(fn () => 'Instance override');
        $dictionary = ['status' => ['draft' => ['Draft', 'warning']]];
        $fields = FormGenerator::makeForModel(
            model: $model,
            exceptColumns: ['owner_id'],
            overwriteColumns: ['quantity' => $formOverride],
            enumDictionary: $dictionary,
            relationManagerView: true,
        );
        $tableOverride = TextColumn::make('quantity');
        $columns = TableGenerator::makeForModel(
            model: $model,
            overwriteColumns: ['quantity' => $tableOverride],
            enumDictionary: $dictionary,
            visibleColumns: ['connection_label'],
            searchableColumns: ['status' => 'both'],
        );
        $entryOverride = TextEntry::make('quantity');
        $infolist = InfolistGenerator::makeForModel(
            model: $model,
            exceptColumns: ['owner_id'],
            overwriteColumns: ['quantity' => $entryOverride],
            enumDictionary: $dictionary,
        );
        $entries = $infolist[0]->getDefaultChildComponents()[0]->getDefaultChildComponents();

        expect($model->getConnectionName())->toBe('secondary')
            ->and(array_keys($fields))->toBe(['id', 'connection_label', 'quantity', 'status'])
            ->and($fields['connection_label'])->toBeInstanceOf(Toggle::class)
            ->and($fields['quantity'])->toBe($formOverride)
            ->and($fields['status']->getOptions())->toBe(['draft' => 'Draft'])
            ->and($columns['quantity'])->toBe($tableOverride)
            ->and($columns['owner_id']->getName())->toBe('owner.title')
            ->and($columns['status']->isGloballySearchable())->toBeTrue()
            ->and($columns['status']->isIndividuallySearchable())->toBeTrue()
            ->and($columns['status']->isToggledHiddenByDefault())->toBeTrue()
            ->and($columns['connection_label']->isToggledHiddenByDefault())->toBeFalse()
            ->and($entries)->toHaveKeys(['id', 'connection_label', 'quantity', 'status'])->not->toHaveKey('name')
            ->and($entries['quantity'])->toBe($entryOverride)
            ->and($infolist)->toHaveCount(1);

        $nativeColumn = collect(Schema::connection('secondary')->getColumns('products'))->firstWhere('name', 'connection_label');
        assert(is_array($nativeColumn));
        expect((new SchemaReader($model))->normalize($nativeColumn)->getType())->toBe('boolean');

        $next = FormGenerator::makeForModel($model, relationManagerView: true);
        $default = FormGenerator::make(Product::class, relationManagerView: true);

        expect($next['connection_label'])->not->toBe($fields['connection_label'])
            ->and($default)->toHaveKey('name')->not->toHaveKey('connection_label')
            ->and((new Product())->getConnectionName())->toBeNull();
    } finally {
        Schema::connection('secondary')->dropIfExists('products');
        Schema::connection('secondary')->dropIfExists('owners');
    }
});

it('retains column construction and relationship handler overrides with model instances', function (): void {
    $model = Product::create(['name' => 'Hook record', 'price' => '1.00']);
    $formGenerator = new class(Product::class) extends FormGenerator
    {
        protected function constructColumnInstance(array $column): Column
        {
            $column['nullable'] = true;

            return parent::constructColumnInstance($column);
        }
    };
    $tableGenerator = new class(Product::class) extends TableGenerator
    {
        protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
        {
            return parent::handleRelationshipColumn($column, $relationshipName, $relationshipTitleColumnName)
                ->state(fn () => 'Custom table state');
        }
    };
    $infolistGenerator = new class(Product::class) extends InfolistGenerator
    {
        protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
        {
            return parent::handleRelationshipColumn($column, $relationshipName, $relationshipTitleColumnName)
                ->state(fn () => 'Custom infolist state');
        }
    };
    $fields = $formGenerator::makeForModel($model, relationManagerView: true);
    $columns = $tableGenerator::makeForModel($model);
    $host = new GeneratorRefactorFormHost();
    $host->setGeneratorTable(Table::make($host)->columns($columns));
    $schema = FilamentSchema::make(new ListProducts())->model($model)->components($infolistGenerator::makeForModel($model));
    $entry = collect($schema->getFlatComponents(withHidden: true))->first(fn ($component) => $component instanceof TextEntry && $component->getName() === 'subject.name');

    expect($fields['name']->isRequired())->toBeFalse()
        ->and($columns['subject']->record($model)->getState())->toBe('Custom table state')
        ->and($entry->getState())->toBe('Custom infolist state');
});

it('displays heterogeneous morph targets using their real name title or model key', function (): void {
    Schema::create('generator_morph_titles', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
    });
    Schema::create('generator_morph_keys', function (Blueprint $table): void {
        $table->string('target_key')->primary();
    });
    // Heterogeneous targets include string keys, so the morph ID must accept them.
    Schema::table('products', fn (Blueprint $table) => $table->string('subject_id')->nullable()->change());
    $preventMissingAttributes = Model::preventsAccessingMissingAttributes();

    try {
        $named = Owner::create(['name' => 'Named target']);
        $titled = GeneratorRefactorTitleTarget::create(['title' => 'Titled target']);
        $keyed = GeneratorRefactorKeyTarget::create(['target_key' => 'custom-key']);
        $untitled = GeneratorRefactorTitleTarget::create(['title' => null]);
        $columns = TableGenerator::make(Product::class);
        $host = new GeneratorRefactorFormHost();
        $host->setGeneratorTable(Table::make($host)->columns($columns));
        Model::preventAccessingMissingAttributes();

        foreach ([[$named, 'Named target'], [$titled, 'Titled target'], [$keyed, 'custom-key'], [$untitled, $untitled->getKey()], [null, null]] as [$target, $expected]) {
            $record = new Product(['name' => 'Wrong referring-model name', 'price' => '1.00']);
            $record->subject()->associate($target);
            $record->save();
            $record = $record->fresh();
            $column = $columns['subject']->record($record);
            $schema = FilamentSchema::make(new ListProducts())->model($record)->components(InfolistGenerator::make(Product::class));
            $entry = collect($schema->getFlatComponents(withHidden: true))->first(fn ($component) => $component instanceof TextEntry && $component->getName() === 'subject.name');

            expect($column->getName())->toBe('subject.name')
                ->and($column->getState())->toBe($expected)
                ->and($entry)->toBeInstanceOf(TextEntry::class)
                ->and($entry->getState())->toBe($expected)
                ->and($column->getUrl())->toBeNull()
                ->and($entry->getUrl())->toBeNull();
        }
    } finally {
        Model::preventAccessingMissingAttributes($preventMissingAttributes);
        Schema::dropIfExists('generator_morph_titles');
        Schema::dropIfExists('generator_morph_keys');
    }
});
