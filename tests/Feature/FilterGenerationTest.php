<?php

use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Eloquent\Casts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Generators\FilterGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationBoundScopedProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationCrossConnectionProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationCustomManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationCustomOwnerKeyProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationCustomResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationDeclaredScopedProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationEncryptedTitleProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationFormHost;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationHiddenTitleProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationJsonTitleProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationPriority;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationReadOnlyManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationReadOnlyResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationRuntimeRelationProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationRuntimeScopedProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationStatus;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\FilterGenerationUnrelatedProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;

require_once __DIR__ . '/../Fixtures/FilterGenerationFixtures.php';

afterEach(function (): void {
    // Do not resolve a fresh connection from configuration that a failing test may have changed.
    $connection = DB::getConnections()['secondary'] ?? null;

    if ($connection === null) {
        return;
    }

    $schema = $connection->getSchemaBuilder();
    $schema->dropIfExists('products');
    $schema->dropIfExists('owners');
});

function filterGenerationBuilder(array $filters, ?Model $model = null): QueryBuilder
{
    $table = Table::make(new ListProducts())
        ->query(($model ?? new Product())->newQuery())
        ->filters($filters);
    $builder = $table->getFilter('queryBuilder');

    expect($builder)->toBeInstanceOf(QueryBuilder::class);

    return $builder;
}

function filterGenerationManager(string $class, ?Owner $owner = null): AutoRelationManager
{
    $manager = new $class();
    $manager->ownerRecord = $owner ?? Owner::create(['name' => 'Filter owner']);
    $manager->pageClass = ListProducts::class;

    return $manager;
}

function filterGenerationManagerTable(AutoRelationManager $manager): Table
{
    $table = Table::make($manager)->relationship(fn () => $manager->getRelationship());
    $resource = $manager::getRelatedResource();

    return $manager->table($resource ? $resource::table($table) : $table);
}

function filterGenerationSecondarySchema(): void
{
    Schema::connection('secondary')->create('owners', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });
    Schema::connection('secondary')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->nullableMorphs('subject');
        $table->string('connection_label')->nullable();
        $table->string('status');
    });
}

it('generates one native query builder with scalar constraints and raw morph columns', function (): void {
    $filters = FilterGenerator::make(Product::class);

    expect($filters)->toHaveCount(1)
        ->and(array_values($filters)[0])->toBeInstanceOf(BaseFilter::class)
        ->and(array_values($filters)[0]->getName())->toBe('queryBuilder');

    $constraints = filterGenerationBuilder($filters)->getConstraints();

    expect($constraints['name'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['description'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['status'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['is_active'])->toBeInstanceOf(BooleanConstraint::class)
        ->and($constraints['id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['id']->isInteger())->toBeTrue()
        ->and($constraints['quantity'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['quantity']->isInteger())->toBeTrue()
        ->and($constraints['price'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['price']->isInteger())->toBeFalse()
        ->and($constraints['published_on'])->toBeInstanceOf(DateConstraint::class)
        ->and($constraints['published_on']->hasTime())->toBeFalse()
        ->and($constraints['published_at'])->toBeInstanceOf(DateConstraint::class)
        ->and($constraints['published_at']->hasTime())->toBeTrue()
        ->and($constraints['created_at']->hasTime())->toBeTrue()
        ->and($constraints['updated_at']->hasTime())->toBeTrue()
        ->and($constraints['deleted_at']->hasTime())->toBeTrue()
        ->and($constraints['subject_type'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['subject_id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints)->not->toHaveKeys(['metadata', 'subject']);
});

it('uses native column nullability for scalar and relationship empty operators', function (): void {
    Schema::table('products', function (Blueprint $table): void {
        $table->boolean('optional_flag')->nullable();
    });
    $constraints = filterGenerationBuilder(FilterGenerator::make(Product::class))->getConstraints();

    foreach (['description', 'published_on', 'published_at', 'deleted_at', 'subject_type', 'subject_id', 'optional_flag'] as $name) {
        expect($constraints[$name]->isNullable())->toBeTrue()
            ->and($constraints[$name]->getOperator('isFilled'))->not->toBeNull();
    }

    foreach (['name', 'quantity', 'price', 'status', 'is_active'] as $name) {
        expect($constraints[$name]->isNullable())->toBeFalse()
            ->and($constraints[$name]->getOperator('isFilled'))->toBeNull();
    }

    expect($constraints['owner']->canBeEmpty())->toBeTrue()
        ->and($constraints['owner']->getOperator('isEmpty'))->not->toBeNull();
});

it('maps native uuid enum and fractional types without treating floats as integers', function (): void {
    Schema::table('products', function (Blueprint $table): void {
        $table->uuid('external_uuid')->nullable();
        $table->enum('native_status', ['draft', 'published']);
        $table->float('rating');
        $table->double('ratio');
        $table->binary('payload')->nullable();
    });
    $constraints = filterGenerationBuilder(FilterGenerator::make(Product::class))->getConstraints();

    expect($constraints['external_uuid'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['native_status'])->toBeInstanceOf(TextConstraint::class)
        ->and($constraints['rating'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['rating']->isInteger())->toBeFalse()
        ->and($constraints['ratio'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['ratio']->isInteger())->toBeFalse()
        ->and($constraints)->not->toHaveKey('payload');
});

it('skips unknown physical column types', function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('SQLite permits a custom physical type for this introspection test.');
    }

    DB::statement('ALTER TABLE products ADD COLUMN opaque opaque_type');

    expect(filterGenerationBuilder(FilterGenerator::make(Product::class))->getConstraints())
        ->not->toHaveKey('opaque');
});

it('honors runtime scalar casts when selecting and configuring constraints', function (string $cast, string $constraintClass, ?bool $integer, ?bool $time): void {
    $model = (new Product())->mergeCasts(['name' => $cast]);
    $constraint = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints()['name'];

    expect($constraint)->toBeInstanceOf($constraintClass);

    if ($integer !== null) {
        expect($constraint->isInteger())->toBe($integer);
    }

    if ($time !== null) {
        expect($constraint->hasTime())->toBe($time);
    }
})->with([
    'string' => ['string', TextConstraint::class, null, null],
    'boolean' => ['boolean', BooleanConstraint::class, null, null],
    'integer' => ['integer', NumberConstraint::class, true, null],
    'int alias' => ['int', NumberConstraint::class, true, null],
    'timestamp on varchar' => ['timestamp', NumberConstraint::class, true, null],
    'float' => ['float', NumberConstraint::class, false, null],
    'double' => ['double', NumberConstraint::class, false, null],
    'real' => ['real', NumberConstraint::class, false, null],
    'decimal' => ['decimal:3', NumberConstraint::class, false, null],
    'date' => ['date:Y-m-d', DateConstraint::class, null, false],
    'immutable date' => ['immutable_date', DateConstraint::class, null, false],
    'datetime' => ['datetime:Y-m-d H:i', DateConstraint::class, null, true],
    'immutable datetime' => ['immutable_datetime', DateConstraint::class, null, true],
]);

it('does not let an enum dictionary expose unsafe casts', function (string $cast): void {
    $model = (new Product())->mergeCasts(['description' => $cast]);
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel(
        model: $model,
        enumDictionary: ['description' => ['secret' => ['Secret', 'danger']]],
    ), $model)->getConstraints();

    expect($constraints)->not->toHaveKey('description');
})->with(['array', 'json', 'object', 'collection', 'encrypted', 'encrypted:array', 'encrypted:collection', 'encrypted:object', 'hashed']);

it('keeps physical datetime columns time-aware when cast to timestamp', function (): void {
    $model = (new Product())->mergeCasts(['published_at' => 'timestamp', 'created_at' => 'timestamp']);
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();

    foreach (['published_at', 'created_at'] as $name) {
        expect($constraints[$name])->toBeInstanceOf(DateConstraint::class)
            ->and($constraints[$name]->hasTime())->toBeTrue();
    }
});

it('does not expose physical JSON through a scalar cast or dictionary', function (string $type): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('SQLite permits both native JSON type declarations for this introspection test.');
    }

    DB::statement("ALTER TABLE products ADD COLUMN structured_payload {$type}");
    $metadata = collect(Schema::getColumns('products'))->firstWhere('name', 'structured_payload');
    expect(strtolower($metadata['type_name']))->toBe($type);

    foreach (['string', 'integer'] as $cast) {
        $model = (new Product())->mergeCasts(['structured_payload' => $cast]);

        foreach ([[], ['structured_payload' => ['secret' => 'Must remain excluded']]] as $dictionary) {
            $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model, enumDictionary: $dictionary), $model)->getConstraints();
            expect($constraints)->not->toHaveKey('structured_payload');
        }
    }
})->with(['json', 'jsonb']);

it('excludes built-in structured cast classes on text before dictionary mapping', function (string $cast): void {
    if (! class_exists(explode(':', $cast, 2)[0])) {
        $this->markTestSkipped('This structured cast is unavailable in the installed Laravel version.');
    }

    $model = (new Product())->mergeCasts(['description' => $cast]);

    foreach ([[], ['description' => ['secret' => 'Must remain excluded']]] as $dictionary) {
        $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model, enumDictionary: $dictionary), $model)->getConstraints();
        expect($constraints)->not->toHaveKey('description');
    }
})->with([
    'array object' => [Casts\AsArrayObject::class],
    'collection' => [Casts\AsCollection::class],
    'enum array object' => [Casts\AsEnumArrayObject::class . ':' . FilterGenerationStatus::class],
    'enum collection' => [Casts\AsEnumCollection::class . ':' . FilterGenerationStatus::class],
    'encrypted array object' => [Casts\AsEncryptedArrayObject::class],
    'encrypted collection' => [Casts\AsEncryptedCollection::class],
    'fluent when available' => [Casts\AsFluent::class],
    'vector when available' => [Casts\AsVector::class],
]);

it('uses dictionary labels instead of color tuples and makes selects searchable and multiple', function (): void {
    $constraints = filterGenerationBuilder(FilterGenerator::make(
        modelClass: Product::class,
        enumDictionary: [
            'status' => ['draft' => ['Draft label', 'warning'], 'published' => 'Published label'],
            'description' => ['short' => ['Short label', 'info']],
            'owner_id' => [1 => ['Owner label', 'primary']],
            'metadata' => ['unsafe' => ['Must not appear', 'danger']],
        ],
    ))->getConstraints();

    expect($constraints['status'])->toBeInstanceOf(SelectConstraint::class)
        ->and($constraints['status']->getOptions())->toBe(['draft' => 'Draft label', 'published' => 'Published label'])
        ->and($constraints['status']->isSearchable())->toBeTrue()
        ->and($constraints['status']->isMultiple())->toBeTrue()
        ->and($constraints['status']->isNullable())->toBeFalse()
        ->and($constraints['description'])->toBeInstanceOf(SelectConstraint::class)
        ->and($constraints['description']->isNullable())->toBeTrue()
        ->and($constraints['owner_id'])->toBeInstanceOf(SelectConstraint::class)
        ->and($constraints['owner_id']->getOptions())->toBe([1 => 'Owner label'])
        ->and($constraints)->not->toHaveKeys(['owner', 'metadata']);
});

it('uses backed enum values and native labels while retaining dictionary priority', function (): void {
    $model = (new Product())->mergeCasts([
        'status' => FilterGenerationStatus::class,
        'quantity' => FilterGenerationPriority::class,
    ]);
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();

    expect($constraints['status'])->toBeInstanceOf(SelectConstraint::class)
        ->and($constraints['status']->getOptions())->toBe(['draft' => 'Draft article', 'published' => 'Published article'])
        ->and($constraints['status']->isSearchable())->toBeTrue()
        ->and($constraints['status']->isMultiple())->toBeTrue()
        ->and($constraints['quantity'])->toBeInstanceOf(SelectConstraint::class)
        ->and($constraints['quantity']->getOptions())->toBe([1 => 'Low', 2 => 'High'])
        ->and($constraints['quantity']->isSearchable())->toBeTrue()
        ->and($constraints['quantity']->isMultiple())->toBeTrue();

    $overridden = filterGenerationBuilder(FilterGenerator::makeForModel(
        model: $model,
        enumDictionary: ['status' => ['draft' => ['Dictionary wins', 'warning']]],
    ), $model)->getConstraints();

    expect($overridden['status']->getOptions())->toBe(['draft' => 'Dictionary wins']);
});

it('applies hidden visible and explicit exclusions before dictionary and relationship mapping', function (): void {
    $model = (new Product())->setHidden(['description', 'owner_id'])->setVisible(['name', 'description', 'owner_id', 'status']);
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel(
        model: $model,
        exceptColumns: ['status'],
        enumDictionary: [
            'description' => ['secret' => 'Hidden'],
            'owner_id' => [1 => 'Hidden owner'],
            'quantity' => [1 => 'Not visible'],
            'status' => ['draft' => 'Excluded'],
        ],
    ), $model)->getConstraints();

    expect(array_keys($constraints))->toBe(['name']);
});

it('returns no filter when no eligible constraints remain', function (): void {
    $model = (new Product())->setVisible(['metadata']);

    expect(FilterGenerator::makeForModel($model))->toBe([])
        ->and(FilterGenerator::make(Product::class, exceptColumns: Schema::getColumnListing('products')))->toBe([]);
});

it('creates fresh query builders constraints and native operators for every call', function (): void {
    $first = filterGenerationBuilder(FilterGenerator::make(Product::class));
    $firstConstraints = $first->getConstraints();
    $firstConstraints['name']->label(fn (): string => 'Changed label')->nullable();
    $firstConstraints['owner']->getOperator('isRelatedTo')->titleAttribute('changed_title');

    $next = filterGenerationBuilder(FilterGenerator::make(Product::class));
    $nextConstraints = $next->getConstraints();

    expect($next)->not->toBe($first)
        ->and($nextConstraints['name'])->not->toBe($firstConstraints['name'])
        ->and($nextConstraints['name']->getLabel())->not->toBe('Changed label')
        ->and($nextConstraints['name']->isNullable())->toBeFalse()
        ->and($nextConstraints['name']->getOperator('contains'))->not->toBe($firstConstraints['name']->getOperator('contains'))
        ->and($nextConstraints['owner']->getOperator('isRelatedTo'))->not->toBe($firstConstraints['owner']->getOperator('isRelatedTo'))
        ->and($nextConstraints['owner']->getOperator('isRelatedTo')->getTitleAttribute())->toBe('name');
});

it('uses a verified belongs-to relationship with a native searchable selectable operator', function (): void {
    $constraints = filterGenerationBuilder(FilterGenerator::make(Product::class))->getConstraints();
    $owner = $constraints['owner'];
    $operator = $owner->getOperator('isRelatedTo');

    expect($owner)->toBeInstanceOf(RelationshipConstraint::class)
        ->and($owner->getRelationshipName())->toBe('owner')
        ->and($owner->isMultiple())->toBeFalse()
        ->and($operator)->toBeInstanceOf(IsRelatedToOperator::class)
        ->and($operator->getTitleAttribute())->toBe('name')
        ->and($operator->isSearchable())->toBeTrue()
        ->and($operator->isMultiple())->toBeTrue()
        ->and($operator->isSearchForcedCaseInsensitive())->toBeTrue()
        ->and($constraints)->not->toHaveKey('owner_id');

    $field = $operator->constraint($owner)->getFormSchema()[0];

    expect($field->isSearchable())->toBeTrue()
        ->and($field->isMultiple())->toBeTrue()
        ->and($field->getRelationshipName())->toBe('owner')
        ->and($field->getRelationshipTitleAttribute())->toBe('name');
});

it('applies a generated native belongs-to operator to the actual database query', function (): void {
    $firstOwner = Owner::create(['name' => 'First owner']);
    $otherOwner = Owner::create(['name' => 'Other owner']);
    $first = Product::create(['owner_id' => $firstOwner->getKey(), 'name' => 'First', 'price' => 10]);
    Product::create(['owner_id' => $otherOwner->getKey(), 'name' => 'Other', 'price' => 20]);
    Product::create(['name' => 'Unowned', 'price' => 30]);
    $constraint = filterGenerationBuilder(FilterGenerator::make(Product::class))->getConstraints()['owner'];
    $operator = $constraint->getOperator('isRelatedTo')
        ->constraint($constraint)
        ->settings(['value' => [$firstOwner->getKey()]])
        ->inverse(false);

    expect($operator->apply(Product::query(), 'owner')->pluck('id')->all())->toBe([$first->getKey()]);
});

it('falls back to raw foreign keys when runtime relationship scopes change SQL or bindings', function (string $modelClass, bool $sameSql): void {
    $model = new $modelClass();
    $model->ownerTenant = 'Tenant A';
    $actual = Relation::noConstraints(fn () => $model->owner())->getQuery()->toBase();
    $native = Relation::noConstraints(fn () => (new $modelClass())->owner())->getQuery()->toBase();

    expect($actual->toSql() === $native->toSql())->toBe($sameSql)
        ->and($actual->getBindings())->not->toBe($native->getBindings());

    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();

    expect($constraints)->not->toHaveKey('owner')
        ->and($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['owner_id']->isInteger())->toBeTrue();
})->with([
    'runtime restriction versus broader class query' => [FilterGenerationRuntimeScopedProduct::class, false],
    'same SQL with another tenant binding' => [FilterGenerationBoundScopedProduct::class, true],
]);

it('retains stable declared relationship scopes in native option searches and value queries', function (): void {
    $allowed = Owner::create(['name' => 'Tenant A']);
    $outside = Owner::create(['name' => 'Tenant B']);
    $model = new FilterGenerationDeclaredScopedProduct(['owner_id' => $allowed->getKey()]);
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();

    expect($constraints['owner'])->toBeInstanceOf(RelationshipConstraint::class)
        ->and($constraints)->not->toHaveKey('owner_id');

    $operator = $constraints['owner']->getOperator('isRelatedTo')->constraint($constraints['owner']);
    expect($operator->getRelationshipQuery()->pluck('owners.id')->all())->toBe([$allowed->getKey()])
        ->and($operator->getRelationshipQuery()->whereKey($outside->getKey())->exists())->toBeFalse();

    $field = $operator->getFormSchema()[0];
    FilamentSchema::make(new FilterGenerationFormHost())
        ->model($model)
        ->statePath('filterGenerationData')
        ->components([$field])
        ->getFlatComponents(withHidden: true);

    expect($field->getSearchResults('Tenant'))->toBe([$allowed->getKey() => 'Tenant A']);
    $field->state([$allowed->getKey(), $outside->getKey()]);
    expect($field->getOptionLabels())->toBe([$allowed->getKey() => 'Tenant A']);
});

it('rejects unsafe connection identities before querying an unavailable related schema', function (string $sourceConnection): void {
    if ($sourceConnection === 'secondary') {
        filterGenerationSecondarySchema();
    }

    config()->set('database.connections.filter_generation_unavailable', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $foreignQueries = [];
    $connectionAttempts = 0;
    $unavailable = DB::connection('filter_generation_unavailable');
    $unavailable->setPdo(function () use (&$connectionAttempts): never {
        $connectionAttempts++;
        throw new RuntimeException('The unavailable related database must never be connected.');
    });
    $unavailable->beforeExecuting(function (string $sql) use (&$foreignQueries): never {
        $foreignQueries[] = $sql;
        throw new RuntimeException('Foreign schema queries must not precede the connection safety check.');
    });
    $model = (new FilterGenerationRuntimeRelationProduct())->setConnection($sourceConnection);
    $model->relatedConnection = 'filter_generation_unavailable';
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();

    expect($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints)->not->toHaveKey('owner')
        ->and($foreignQueries)->toBe([])
        ->and($connectionAttempts)->toBe(0);
})->with(['testing', 'secondary']);

it('falls back to a raw foreign key when native relationship semantics are unsafe', function (string $modelClass): void {
    filterGenerationSecondarySchema();
    $constraints = filterGenerationBuilder(FilterGenerator::make($modelClass), new $modelClass())->getConstraints();

    expect($constraints)->toHaveKey('owner_id')->not->toHaveKey('owner')
        ->and($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints['owner_id']->isInteger())->toBeTrue()
        ->and($constraints['owner_id']->isNullable())->toBeTrue();
})->with([
    'cross connection' => [FilterGenerationCrossConnectionProduct::class],
    'non-primary owner key' => [FilterGenerationCustomOwnerKeyProduct::class],
    'hidden title' => [FilterGenerationHiddenTitleProduct::class],
    'encrypted title' => [FilterGenerationEncryptedTitleProduct::class],
    'JSON title' => [FilterGenerationJsonTitleProduct::class],
]);

it('rejects runtime related class table and connection changes that native class injection loses', function (string $change): void {
    filterGenerationSecondarySchema();
    Schema::create('filter_generation_owners', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    try {
        $model = new FilterGenerationRuntimeRelationProduct();

        match ($change) {
            'class' => $model->alternateRelatedClass = true,
            'table' => $model->relatedTable = 'filter_generation_owners',
            'connection' => $model->relatedConnection = 'secondary',
        };

        $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();
        $fresh = filterGenerationBuilder(FilterGenerator::make(FilterGenerationRuntimeRelationProduct::class))->getConstraints();

        expect($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
            ->and($constraints)->not->toHaveKey('owner')
            ->and($fresh['owner'])->toBeInstanceOf(RelationshipConstraint::class);
    } finally {
        Schema::dropIfExists('filter_generation_owners');
    }
})->with(['class', 'table', 'connection']);

it('rejects runtime source table changes without losing scalar filtering', function (): void {
    Schema::create('filter_generation_products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('runtime_label');
    });

    try {
        $model = (new Product())->setTable('filter_generation_products');
        $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();

        expect($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
            ->and($constraints['runtime_label'])->toBeInstanceOf(TextConstraint::class)
            ->and($constraints)->not->toHaveKeys(['owner', 'name']);
    } finally {
        Schema::dropIfExists('filter_generation_products');
    }
});

it('does not scan unrelated relationship methods or accept a mismatched foreign key', function (): void {
    $constraints = filterGenerationBuilder(FilterGenerator::make(FilterGenerationUnrelatedProduct::class))->getConstraints();

    expect($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints)->not->toHaveKeys(['owner', 'unrelatedRelationship']);
});

it('uses the actual model connection table and runtime casts without contaminating subsequent calls', function (): void {
    filterGenerationSecondarySchema();
    $model = (new Product())->setConnection('secondary')->mergeCasts(['connection_label' => 'boolean']);
    $constraints = filterGenerationBuilder(FilterGenerator::makeForModel($model), $model)->getConstraints();
    $default = filterGenerationBuilder(FilterGenerator::make(Product::class))->getConstraints();

    expect($constraints['connection_label'])->toBeInstanceOf(BooleanConstraint::class)
        ->and($constraints['owner_id'])->toBeInstanceOf(NumberConstraint::class)
        ->and($constraints)->not->toHaveKeys(['name', 'owner'])
        ->and($default['name'])->toBeInstanceOf(TextConstraint::class)
        ->and($default['owner'])->toBeInstanceOf(RelationshipConstraint::class)
        ->and($default)->not->toHaveKey('connection_label')
        ->and((new Product())->getConnectionName())->toBeNull();
});

it('uses the fallback model when a table has no relationship', function (): void {
    $model = (new Product())->mergeCasts(['name' => 'boolean']);
    $table = Table::make(new ListProducts())->query(Product::query());
    $table->filters(FilterGenerator::makeForTable($table, $model, exceptColumns: ['status']));
    $constraints = $table->getFilter('queryBuilder')->getConstraints();

    expect($constraints['name'])->toBeInstanceOf(BooleanConstraint::class)
        ->and($constraints)->not->toHaveKey('status');
});

it('uses a table relationship actual model and suppresses owner columns before dictionaries', function (string $relationship, array $excluded): void {
    filterGenerationSecondarySchema();
    $owner = (new FilterGenerationOwner())->setConnection('secondary');
    $table = Table::make(new ListProducts())->relationship(fn () => $owner->{$relationship}());
    $table->filters(FilterGenerator::makeForTable(
        $table,
        new Product(),
        exceptColumns: ['status'],
        enumDictionary: [
            'owner_id' => [1 => 'Owner'],
            'subject_type' => ['owner' => 'Owner type'],
            'subject_id' => [1 => 'Subject'],
        ],
    ));
    $constraints = $table->getFilter('queryBuilder')->getConstraints();

    expect($constraints)->toHaveKey('connection_label')->not->toHaveKeys([...$excluded, 'name', 'status']);

    if ($relationship === 'products') {
        expect($constraints['connection_label'])->toBeInstanceOf(BooleanConstraint::class);
    }
})->with([
    'has many' => ['products', ['owner_id', 'owner']],
    'has one' => ['singleProduct', ['owner_id', 'owner']],
    'morph many' => ['morphProducts', ['subject_id', 'subject_type', 'subject']],
    'morph one' => ['morphProduct', ['subject_id', 'subject_type', 'subject']],
]);

it('prepends generated resource filters while retaining custom and soft-delete filters in read-only mode', function (string $resource): void {
    $table = $resource::table(Table::make(new ListProducts())->query(Product::query()));
    $filters = $table->getFilters();
    $constraints = $filters['queryBuilder']->getConstraints();

    expect(array_keys($filters))->toBe(['queryBuilder', 'manual', 'trashed'])
        ->and($filters['queryBuilder'])->toBeInstanceOf(QueryBuilder::class)
        ->and($filters['trashed'])->toBeInstanceOf(TrashedFilter::class)
        ->and($constraints)->toHaveKeys(['description', 'quantity', 'status'])
        ->and($constraints['status']->getOptions())->toBe(['draft' => 'Draft filter', 'published' => 'Published filter']);
})->with([[FilterGenerationResource::class], [FilterGenerationReadOnlyResource::class]]);

it('lets an explicit resource query builder replace rather than duplicate the generated native name', function (): void {
    $table = FilterGenerationCustomResource::table(Table::make(new ListProducts())->query(Product::query()));
    $filters = $table->getFilters();

    expect(array_keys($filters))->toBe(['queryBuilder', 'manual', 'trashed'])
        ->and($filters['queryBuilder']->getLabel())->toBe('Custom query builder')
        ->and(array_keys($filters['queryBuilder']->getConstraints()))->toBe(['customMarker'])
        ->and($filters['trashed'])->toBeInstanceOf(TrashedFilter::class);
});

it('generates standalone manager filters independently of visible table columns and read-only actions', function (string $managerClass): void {
    $table = filterGenerationManagerTable(filterGenerationManager($managerClass));
    $filters = $table->getFilters();
    $constraints = $filters['queryBuilder']->getConstraints();

    expect(array_keys($filters))->toBe(['queryBuilder', 'manual', 'trashed'])
        ->and($constraints)->toHaveKeys(['name', 'description', 'quantity', 'status'])->not->toHaveKeys(['owner', 'owner_id'])
        ->and($constraints['status']->getOptions())->toBe(['draft' => 'Draft filter'])
        ->and($filters['trashed'])->toBeInstanceOf(TrashedFilter::class);
})->with([[FilterGenerationManager::class], [FilterGenerationReadOnlyManager::class]]);

it('lets explicit standalone manager query builders replace the generated filter', function (): void {
    $table = filterGenerationManagerTable(filterGenerationManager(FilterGenerationCustomManager::class));
    $filters = $table->getFilters();

    expect(array_keys($filters))->toBe(['queryBuilder', 'manual', 'trashed'])
        ->and($filters['queryBuilder']->getLabel())->toBe('Custom query builder')
        ->and(array_keys($filters['queryBuilder']->getConstraints()))->toBe(['customMarker']);
});

it('inherits resource filters in resource-backed managers without duplication or owner constraints', function (string $resource): void {
    $class = RelationManagerMounter::makeFromResource(
        resource: $resource,
        relation: 'products',
        associateAttachActions: false,
    );
    $table = filterGenerationManagerTable(filterGenerationManager($class));
    $filters = $table->getFilters();
    $constraints = $filters['queryBuilder']->getConstraints();

    expect(array_keys($filters))->toBe(['queryBuilder', 'manual', 'trashed'])
        ->and($filters['trashed'])->toBeInstanceOf(TrashedFilter::class)
        ->and($constraints)->not->toHaveKeys(['owner', 'owner_id']);

    if ($resource === FilterGenerationCustomResource::class) {
        expect(array_keys($constraints))->toBe(['customMarker'])
            ->and($filters['queryBuilder']->getLabel())->toBe('Custom query builder');
    } else {
        expect($constraints)->toHaveKeys(['name', 'description', 'quantity', 'status'])
            ->and($constraints['status']->getOptions())->toBe(['draft' => 'Draft filter', 'published' => 'Published filter']);
    }
})->with([[FilterGenerationResource::class], [FilterGenerationReadOnlyResource::class], [FilterGenerationCustomResource::class]]);

it('integrates actual relationship connections and owner suppression in mounted managers', function (bool $resourceBacked, string $relationship, array $excluded): void {
    filterGenerationSecondarySchema();
    $owner = (new FilterGenerationOwner())->setConnection('secondary');
    $class = $resourceBacked
        ? RelationManagerMounter::makeFromResource(
            resource: FilterGenerationReadOnlyResource::class,
            relation: $relationship,
            associateAttachActions: false,
        )
        : RelationManagerMounter::makeStandalone(
            relation: $relationship,
            visibleColumns: ['connection_label'],
            enumDictionary: ['status' => ['draft' => ['Draft filter', 'warning']]],
            associateAttachActions: false,
            readOnly: true,
        );
    $table = filterGenerationManagerTable(filterGenerationManager($class, $owner));
    $filters = $table->getFilters();
    $constraints = $filters['queryBuilder']->getConstraints();

    expect($constraints)->toHaveKeys(['connection_label', 'status'])->not->toHaveKeys([...$excluded, 'name'])
        ->and($constraints['status']->getOptions())->toHaveKey('draft', 'Draft filter')
        ->and($filters['trashed'])->toBeInstanceOf(TrashedFilter::class);

    if ($relationship === 'products') {
        expect($constraints['connection_label'])->toBeInstanceOf(BooleanConstraint::class);
    }
})->with([
    'standalone has many' => [false, 'products', ['owner_id', 'owner']],
    'standalone has one' => [false, 'singleProduct', ['owner_id', 'owner']],
    'standalone morph many' => [false, 'morphProducts', ['subject_type', 'subject_id', 'subject']],
    'standalone morph one' => [false, 'morphProduct', ['subject_type', 'subject_id', 'subject']],
    'resource has many' => [true, 'products', ['owner_id', 'owner']],
    'resource has one' => [true, 'singleProduct', ['owner_id', 'owner']],
    'resource morph many' => [true, 'morphProducts', ['subject_type', 'subject_id', 'subject']],
    'resource morph one' => [true, 'morphProduct', ['subject_type', 'subject_id', 'subject']],
]);
