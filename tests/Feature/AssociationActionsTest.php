<?php

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\AssociateAction;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\Enums\ActionStatus;
use Filament\Schemas\Schema as ActionSchema;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue11AssociationOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue11AssociationProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue11AssociationResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue11ReadOnlyAssociationResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;

require_once __DIR__ . '/../Fixtures/Issue11AssociationActionFixtures.php';

dataset('issue11 association routes', [
    'standalone' => [false],
    'resource-backed' => [true],
]);

function issue11AssociationTable(
    bool $resourceBacked,
    string $relation = 'products',
    ?Issue11AssociationOwner $owner = null,
    ?bool $enabled = null,
    bool $readOnly = false,
    ?string $inverse = null,
): Table {
    $resource = $readOnly ? Issue11ReadOnlyAssociationResource::class : Issue11AssociationResource::class;
    $class = $resourceBacked
        ? RelationManagerMounter::makeFromResource($resource, $relation, associateAttachActions: $enabled)
        : RelationManagerMounter::makeStandalone($relation, ['name'], associateAttachActions: $enabled, readOnly: $readOnly);
    $manager = new $class;
    $manager->ownerRecord = $owner ?? Issue11AssociationOwner::create(['name' => 'Owner']);
    $manager->pageClass = ListProducts::class;

    $inverse ??= match ($relation) {
        'morphProducts' => 'subject',
        'customProducts' => 'issue11Owner',
        'customMorphProducts' => 'issue11Subject',
        default => 'owner',
    };
    $table = Table::make($manager)
        ->relationship(fn () => $manager->getRelationship())
        ->inverseRelationship($inverse);

    return $manager->table($resourceBacked ? $resource::table($table) : $table);
}

function issue11AssertAssociationActions(Table $table, bool $enabled): void
{
    if ($enabled) {
        expect($table->getAction('associate'))->toBeInstanceOf(AssociateAction::class)
            ->and($table->getAction('dissociate'))->toBeInstanceOf(DissociateAction::class)
            ->and($table->getBulkAction('dissociate'))->toBeInstanceOf(DissociateBulkAction::class)
            ->and(array_map(fn (Action | ActionGroup $action): string => $action->getName(), $table->getHeaderActions()))->toContain('associate')
            ->and(array_map(fn (Action | ActionGroup $action): string => $action->getName(), $table->getRecordActions()))->toContain('dissociate');
    } else {
        expect($table->hasAction('associate'))->toBeFalse()
            ->and($table->hasAction('dissociate'))->toBeFalse()
            ->and($table->hasBulkAction('dissociate'))->toBeFalse();
    }

    expect($table->hasAction('attach'))->toBeFalse()
        ->and($table->hasAction('detach'))->toBeFalse()
        ->and($table->hasBulkAction('detach'))->toBeFalse();
}

function issue11AssertNoRelationshipActions(Table $table): void
{
    foreach (['associate', 'dissociate', 'attach', 'detach'] as $name) {
        expect($table->hasAction($name))->toBeFalse()
            ->and($table->hasBulkAction($name))->toBeFalse();
    }
}

function issue11ChangeNullableColumn(string $column, bool $nullable, string $connection = 'testing'): void
{
    Schema::connection($connection)->table('products', function (Blueprint $table) use ($column, $nullable): void {
        if (str_ends_with($column, '_type') || str_ends_with($column, '_kind')) {
            $table->string($column)->nullable($nullable)->change();
        } else {
            $table->unsignedBigInteger($column)->nullable($nullable)->change();
        }
    });
}

function issue11CreateRequiredPivot(string $relation): string
{
    $morph = $relation === 'morphLinkedProducts';
    $name = $morph ? 'issue11_association_productables' : 'issue11_association_owner_product';

    Schema::create($name, function (Blueprint $table) use ($morph): void {
        $table->foreignId($morph ? 'subject_id' : 'owner_id');
        $table->foreignId('product_id');

        if ($morph) {
            $table->string('subject_type');
        }
    });

    return $name;
}

it('only offers has-many association actions when the real foreign key is nullable', function (bool $nullable, bool $resourceBacked): void {
    issue11ChangeNullableColumn('owner_id', $nullable);

    $table = issue11AssociationTable($resourceBacked);

    issue11AssertAssociationActions($table, $nullable);
    expect($table->getAction('create'))->not->toBeNull();
})->with(['nullable' => [true], 'required' => [false]])->with('issue11 association routes');

it('requires both morph columns to be nullable before offering association actions', function (bool $nullableId, bool $nullableType, bool $resourceBacked): void {
    issue11ChangeNullableColumn('subject_id', $nullableId);
    issue11ChangeNullableColumn('subject_type', $nullableType);

    issue11AssertAssociationActions(
        issue11AssociationTable($resourceBacked, 'morphProducts'),
        $nullableId && $nullableType,
    );
})->with([
    'both nullable' => [true, true],
    'required ID and nullable type' => [false, true],
    'nullable ID and required type' => [true, false],
    'both required' => [false, false],
])->with('issue11 association routes');

it('reads custom relationship column names instead of assuming owner_id or subject_type', function (string $relation, string $column, bool $nullable, bool $resourceBacked): void {
    Schema::table('products', function (Blueprint $table): void {
        $table->unsignedBigInteger('issue11_owner_key')->nullable();
        $table->unsignedBigInteger('issue11_subject_key')->nullable();
        $table->string('issue11_subject_kind')->nullable();
    });
    issue11ChangeNullableColumn($column, $nullable);

    issue11AssertAssociationActions(issue11AssociationTable($resourceBacked, $relation), $nullable);
})->with([
    'custom nullable FK' => ['customProducts', 'issue11_owner_key', true],
    'custom required FK' => ['customProducts', 'issue11_owner_key', false],
    'custom nullable morph FK' => ['customMorphProducts', 'issue11_subject_key', true],
    'custom required morph FK' => ['customMorphProducts', 'issue11_subject_key', false],
    'custom nullable morph discriminator' => ['customMorphProducts', 'issue11_subject_kind', true],
    'custom required morph discriminator' => ['customMorphProducts', 'issue11_subject_kind', false],
])->with('issue11 association routes');

it('fails closed when a relationship column is missing', function (string $relation, string $column, bool $resourceBacked): void {
    Schema::table('products', function (Blueprint $table) use ($relation, $column): void {
        if ($relation === 'morphProducts') {
            $table->dropIndex(['subject_type', 'subject_id']);
        }

        $table->dropColumn($column);
    });

    expect(collect(Schema::getColumns('products'))->pluck('name')->all())->not->toContain($column);
    issue11AssertAssociationActions(issue11AssociationTable($resourceBacked, $relation), false);
})->with([
    'missing has-many FK' => ['products', 'owner_id'],
    'missing morph FK' => ['morphProducts', 'subject_id'],
    'missing morph discriminator' => ['morphProducts', 'subject_type'],
])->with('issue11 association routes');

it('fails closed when native column inspection finds no related table', function (string $relation, bool $resourceBacked): void {
    Schema::drop('products');

    issue11AssertAssociationActions(issue11AssociationTable($resourceBacked, $relation), false);
})->with(['has-many' => ['products'], 'morph-many' => ['morphProducts']])->with('issue11 association routes');

it('does not let an explicit true flag bypass required relationship columns', function (string $relation, string $column, bool $resourceBacked): void {
    issue11ChangeNullableColumn($column, false);

    issue11AssertAssociationActions(issue11AssociationTable($resourceBacked, $relation, enabled: true), false);
})->with([
    'has-many' => ['products', 'owner_id'],
    'morph-many' => ['morphProducts', 'subject_type'],
])->with('issue11 association routes');

it('preserves the existing inverse relationship method guard', function (string $relation, bool $resourceBacked): void {
    issue11AssertAssociationActions(
        issue11AssociationTable($resourceBacked, $relation, inverse: 'issue11MissingInverse'),
        false,
    );
})->with(['has-many' => ['products'], 'morph-many' => ['morphProducts']])->with('issue11 association routes');

it('keeps the explicit false flag as an opt-out for every relationship action family', function (string $relation, bool $resourceBacked): void {
    $table = issue11AssociationTable($resourceBacked, $relation, enabled: false);

    issue11AssertNoRelationshipActions($table);
    expect($table->getAction('create'))->not->toBeNull();
})->with([
    'has-many' => ['products'],
    'morph-many' => ['morphProducts'],
    'belongs-to-many' => ['linkedProducts'],
    'morph-to-many' => ['morphLinkedProducts'],
])->with('issue11 association routes');

it('disables every relationship action family for read-only managers and resources', function (string $relation, bool $resourceBacked): void {
    $table = issue11AssociationTable($resourceBacked, $relation, enabled: true, readOnly: true);

    issue11AssertNoRelationshipActions($table);
    expect($table->getAction('create'))->toBeNull();
})->with([
    'has-many' => ['products'],
    'morph-many' => ['morphProducts'],
    'belongs-to-many' => ['linkedProducts'],
    'morph-to-many' => ['morphLinkedProducts'],
])->with('issue11 association routes');

it('uses the related models inherited connection and native table prefix instead of the default connection', function (string $relation, string $column, bool $nullable, bool $resourceBacked): void {
    issue11ChangeNullableColumn($column, ! $nullable);
    $schema = Schema::connection('secondary');
    $schema->create('owners', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    try {
        $schema->create('products', function (Blueprint $table) use ($nullable): void {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable($nullable);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type')->nullable($nullable);
            $table->string('name');
        });
        $owner = (new Issue11AssociationOwner)->setConnection('secondary');
        $owner->forceFill(['name' => 'Secondary owner'])->save();
        $related = $owner->{$relation}()->getRelated();

        expect((new Issue11AssociationProduct)->getConnectionName())->toBeNull()
            ->and($related->getConnectionName())->toBe('secondary')
            ->and($related->getTable())->toBe('products')
            ->and($related->getConnection()->getTablePrefix())->toBe('secondary_')
            ->and($related->getConnection()->getSchemaBuilder()->getColumns('products'))->not->toBeEmpty();

        issue11AssertAssociationActions(issue11AssociationTable($resourceBacked, $relation, $owner), $nullable);
    } finally {
        $schema->dropIfExists('products');
        $schema->dropIfExists('owners');
    }
})->with([
    'secondary nullable FK, default required' => ['products', 'owner_id', true],
    'secondary required FK, default nullable' => ['products', 'owner_id', false],
    'secondary nullable morph type, default required' => ['morphProducts', 'subject_type', true],
    'secondary required morph type, default nullable' => ['morphProducts', 'subject_type', false],
])->with('issue11 association routes');

it('rechecks live nullability on every mount without caching the default action decision', function (string $relation, string $column, bool $resourceBacked): void {
    $owner = Issue11AssociationOwner::create(['name' => 'Owner']);
    $first = issue11AssociationTable($resourceBacked, $relation, $owner);
    issue11AssertAssociationActions($first, true);

    issue11ChangeNullableColumn($column, false);
    $second = issue11AssociationTable($resourceBacked, $relation, $owner);
    expect($second->getLivewire()::class)->toBe($first->getLivewire()::class);
    issue11AssertAssociationActions($second, false);

    issue11ChangeNullableColumn($column, true);
    $third = issue11AssociationTable($resourceBacked, $relation, $owner);
    expect($third->getLivewire()::class)->toBe($first->getLivewire()::class);
    issue11AssertAssociationActions($third, true);
})->with([
    'has-many FK' => ['products', 'owner_id'],
    'morph FK' => ['morphProducts', 'subject_id'],
    'morph discriminator' => ['morphProducts', 'subject_type'],
])->with('issue11 association routes');

it('executes native getColumns inspection on repeated mounts even without a schema change', function (bool $resourceBacked): void {
    $connection = DB::connection();
    $connection->enableQueryLog();

    try {
        $connection->flushQueryLog();
        $connection->getSchemaBuilder()->getColumns('products');
        $queries = $connection->getQueryLog();
        $inspection = $queries[array_key_last($queries)];
        $owner = Issue11AssociationOwner::create(['name' => 'Owner']);

        $class = $resourceBacked
            ? RelationManagerMounter::makeFromResource(Issue11AssociationResource::class, 'products')
            : RelationManagerMounter::makeStandalone('products', ['name']);

        for ($mount = 0; $mount < 2; $mount++) {
            $manager = new $class;
            $manager->ownerRecord = $owner;
            $manager->pageClass = ListProducts::class;
            $table = Table::make($manager)
                ->relationship(fn () => $manager->getRelationship())
                ->inverseRelationship('owner');

            if ($resourceBacked) {
                $table = Issue11AssociationResource::table($table);
            }

            // Resource column generation must not satisfy the eligibility inspection assertion.
            $connection->flushQueryLog();
            issue11AssertAssociationActions($manager->table($table), true);

            expect(collect($connection->getQueryLog())->contains(
                fn (array $query): bool => $query['query'] === $inspection['query'] && $query['bindings'] === $inspection['bindings'],
            ))->toBeTrue();
        }
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
})->with('issue11 association routes');

it('persists native single and bulk dissociation when the relationship columns are nullable', function (string $relation, bool $resourceBacked): void {
    $owner = Issue11AssociationOwner::create(['name' => 'Owner']);
    $records = collect(['Single', 'Bulk one', 'Bulk two', 'Unselected'])->map(
        fn (string $name) => $owner->{$relation}()->create(['name' => $name]),
    );
    $table = issue11AssociationTable($resourceBacked, $relation, $owner);
    $manager = $table->getLivewire();
    assert($manager instanceof AutoRelationManager);
    (new ReflectionProperty($manager, 'table'))->setValue($manager, $table);
    $single = $table->getAction('dissociate')->record($records[0]);

    $single->call();

    expect($single->getStatus())->toBe(ActionStatus::Success);
    $manager->selectedTableRecords = [$records[1]->getKey(), $records[2]->getKey()];
    $bulk = $table->getBulkAction('dissociate');
    $bulk->call();

    expect($bulk->getStatus())->toBe(ActionStatus::Success)
        ->and($bulk->getTotalSelectedRecordsCount())->toBe(2)
        ->and($owner->{$relation}()->pluck('products.id')->all())->toBe([$records[3]->getKey()]);

    foreach ($records->take(3) as $record) {
        $fresh = $record->fresh();
        expect($fresh)->not->toBeNull();

        if ($relation === 'morphProducts') {
            expect($fresh->subject_id)->toBeNull()->and($fresh->subject_type)->toBeNull();
        } else {
            expect($fresh->owner_id)->toBeNull();
        }
    }
})->with(['has-many' => ['products'], 'morph-many' => ['morphProducts']])->with('issue11 association routes');

it('keeps attach and detach actions enabled and persists native pivot insertion and deletion with required columns', function (string $relation, bool $resourceBacked): void {
    $pivot = issue11CreateRequiredPivot($relation);

    try {
        $columns = collect(Schema::getColumns($pivot))->keyBy('name');
        foreach ($columns as $column) {
            expect($column['nullable'])->toBeFalse();
        }

        $owner = Issue11AssociationOwner::create(['name' => 'Owner']);
        $records = collect(['Single', 'Bulk one', 'Bulk two'])->map(
            fn (string $name) => Issue11AssociationProduct::create(['name' => $name]),
        );
        $table = issue11AssociationTable($resourceBacked, $relation, $owner);
        $manager = $table->getLivewire();
        assert($manager instanceof AutoRelationManager);
        (new ReflectionProperty($manager, 'table'))->setValue($manager, $table);

        expect($table->getAction('attach'))->toBeInstanceOf(AttachAction::class)
            ->and($table->getAction('detach'))->toBeInstanceOf(DetachAction::class)
            ->and($table->getBulkAction('detach'))->toBeInstanceOf(DetachBulkAction::class)
            ->and($table->getAction('associate'))->toBeNull()
            ->and($table->getAction('dissociate'))->toBeNull()
            ->and($table->getBulkAction('dissociate'))->toBeNull();

        foreach ($records as $record) {
            $attach = $table->getAction('attach')->data(['recordId' => $record->getKey()]);
            $attach->call(['schema' => ActionSchema::make($manager)->model(Issue11AssociationProduct::class)]);
            expect($attach->getStatus())->toBe(ActionStatus::Success);
        }

        expect(DB::table($pivot)->count())->toBe(3)
            ->and($owner->{$relation}()->count())->toBe(3);

        foreach (DB::table($pivot)->get() as $row) {
            expect($row->product_id)->not->toBeNull();
            if ($relation === 'morphLinkedProducts') {
                expect($row->subject_id)->toBe($owner->getKey())
                    ->and($row->subject_type)->toBe($owner->getMorphClass());
            } else {
                expect($row->owner_id)->toBe($owner->getKey());
            }
        }

        $detach = $table->getAction('detach')->record($owner->{$relation}()->findOrFail($records[0]->getKey()));
        $detach->call();
        expect($detach->getStatus())->toBe(ActionStatus::Success)
            ->and(DB::table($pivot)->count())->toBe(2);

        $manager->selectedTableRecords = [$records[1]->getKey(), $records[2]->getKey()];
        $bulk = $table->getBulkAction('detach');
        $bulk->call();

        expect($bulk->getStatus())->toBe(ActionStatus::Success)
            ->and($bulk->getTotalSelectedRecordsCount())->toBe(2)
            ->and(DB::table($pivot)->count())->toBe(0)
            ->and(Issue11AssociationProduct::count())->toBe(3);
    } finally {
        Schema::dropIfExists($pivot);
    }
})->with(['belongs-to-many' => ['linkedProducts'], 'morph-to-many' => ['morphLinkedProducts']])->with('issue11 association routes');
