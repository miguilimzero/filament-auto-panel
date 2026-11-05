<?php

use Filament\Actions\Action;

use Filament\Actions\Enums\ActionStatus;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Miguilim\FilamentAutoPanel\AutoAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleGuardedListPage;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleGuardedManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleGuardedViewPage;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleListPage;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleViewPage;

require_once __DIR__ . '/../Fixtures/ActionLifecycleFixtures.php';

it('preserves placement defaults and provides reversible fluent flags', function (string $class, array $defaults): void {
    $action = $class::make('placement');
    $properties = ['showOnBulkAction', 'showOnTable', 'showOnViewPage', 'showOnListPage'];

    expect(array_map(fn (string $property): bool => $action->{$property}, $properties))->toBe($defaults);

    foreach ($properties as $property) {
        expect($action->{$property}())->toBe($action)
            ->and($action->{$property})->toBeTrue()
            ->and($action->{$property}(false))->toBe($action)
            ->and($action->{$property})->toBeFalse();
    }
})->with([
    [AutoAction::class, [false, false, false, false]],
    [AutoCreateAction::class, [false, false, false, true]],
    [AutoEditAction::class, [false, false, true, false]],
]);

it('adapts a record action to the documented records collection', function (): void {
    $record = new ActionLifecycleRecord;

    expect(AutoAction::make('records')->record($record)->getSelectedRecords()->all())->toBe([$record]);
});

it('runs native create and edit callbacks with the correct assignment policy', function (string $hostClass, bool $intrusive): void {
    $host = new $hostClass;
    $create = AutoCreateAction::make()->livewire($host)->model(ActionLifecycleRecord::class)
        ->data(['name' => 'Created', 'description' => 'Hidden create value']);
    $schema = Schema::make($host)->model(ActionLifecycleRecord::class);

    $create->call(['schema' => $schema]);
    $record = $create->getRecord();

    expect($record->exists)->toBeTrue()
        ->and($record->fresh()->description)->toBe($intrusive ? 'Hidden create value' : null)
        ->and($create->getStatus())->toBe(ActionStatus::Success)
        ->and($schema->getRecord())->toBe($record)
        ->and(Model::isUnguarded())->toBeFalse();

    $edit = AutoEditAction::make()->livewire($host)->record($record)
        ->mutateDataUsing(fn (array $data): array => [...$data, 'name' => 'Mutated edit'])
        ->data(['name' => 'Edited', 'description' => 'Hidden edit value']);
    $edit->call();

    expect($record->fresh()->name)->toBe('Mutated edit')
        ->and($record->fresh()->description)->toBe($intrusive ? 'Hidden edit value' : null)
        ->and($record->getHidden())->toBe(['description'])
        ->and($edit->getStatus())->toBe(ActionStatus::Success)
        ->and(Model::isUnguarded())->toBeFalse();
})->with([
    [ActionLifecycleListPage::class, true],
    [ActionLifecycleGuardedListPage::class, false],
    [ActionLifecycleManager::class, true],
    [ActionLifecycleGuardedManager::class, false],
]);

it('fills edit forms without changing the callers hidden attributes', function (string $hostClass, bool $intrusive): void {
    $host = new $hostClass;
    $record = (new ActionLifecycleRecord)->forceFill(['name' => 'Visible', 'description' => 'Secret']);
    $schema = Schema::make($host)->model($record)->statePath('actionData')->components([
        TextInput::make('name'),
        TextInput::make('description'),
    ]);
    $edit = AutoEditAction::make()->livewire($host)->record($record);

    $edit->mount(['schema' => $schema]);

    expect($host->actionData['name'])->toBe('Visible')
        ->and($host->actionData['description'] ?? null)->toBe($intrusive ? 'Secret' : null)
        ->and($record->getHidden())->toBe(['description'])
        ->and($record->attributesToArray())->not->toHaveKey('description');
})->with([
    [ActionLifecycleListPage::class, true],
    [ActionLifecycleGuardedListPage::class, false],
    [ActionLifecycleManager::class, true],
    [ActionLifecycleGuardedManager::class, false],
]);

it('preserves custom using and mutateRecordDataUsing hooks', function (): void {
    $host = new ActionLifecycleManager;
    $record = (new ActionLifecycleRecord)->forceFill(['name' => 'Original', 'description' => 'Secret']);
    $create = AutoCreateAction::make()->livewire($host)->using(fn (): Model => $record);

    expect($create->process(fn () => throw new LogicException('Default should not run')))->toBe($record);

    $schema = Schema::make($host)->model($record)->statePath('actionData')->components([TextInput::make('name')]);
    $edit = AutoEditAction::make()->livewire($host)->record($record)
        ->mutateRecordDataUsing(fn (array $data): array => [...$data, 'name' => 'Custom fill']);
    $edit->mount(['schema' => $schema]);

    expect($host->actionData['name'])->toBe('Custom fill');

    $edit->using(fn (): string => 'custom edit');
    expect($edit->process(null))->toBe('custom edit');
});

it('restores mass assignment protection after an intrusive callback throws', function (): void {
    foreach ([AutoCreateAction::class, AutoEditAction::class] as $class) {
        $action = $class::make()->livewire(new ActionLifecycleManager)->using(function (): void {
            expect(Model::isUnguarded())->toBeTrue();

            throw new RuntimeException('Persistence failed');
        });

        expect(fn () => $action->process(null))->toThrow(RuntimeException::class, 'Persistence failed')
            ->and(Model::isUnguarded())->toBeFalse();
    }
});

it('creates through a standalone managers has-many relationship', function (): void {
    $owner = ActionLifecycleOwner::create(['name' => 'Owner']);
    $host = new ActionLifecycleManager;
    $host->ownerRecord = $owner;
    $create = AutoCreateAction::make()->livewire($host)->model(ActionLifecycleRecord::class)
        ->relationship(fn () => $host->getRelationship())
        ->data(['name' => 'Related', 'description' => 'Secret']);

    $create->call(['schema' => Schema::make($host)->model(ActionLifecycleRecord::class)]);

    expect($create->getRecord()->fresh()->owner_id)->toBe($owner->getKey())
        ->and($owner->products()->first()->description)->toBe('Secret');
});

it('uses native belongs-to-many create, fill, and edit pivot behavior', function (): void {
    DatabaseSchema::create('action_lifecycle_owner_product', function (Blueprint $table): void {
        $table->foreignId('owner_id');
        $table->foreignId('product_id');
        $table->string('note');
    });

    try {
        $owner = ActionLifecycleOwner::create(['name' => 'Owner']);
        $host = new ActionLifecycleManager;
        $table = Table::make($host)->relationship(fn () => $owner->linkedProducts());
        $create = AutoCreateAction::make()->livewire($host)->table($table)->model(ActionLifecycleRecord::class)
            ->data(['name' => 'Linked', 'description' => 'Hidden', 'note' => 'Created pivot']);
        $create->call(['schema' => Schema::make($host)->model(ActionLifecycleRecord::class)]);
        $record = $owner->linkedProducts()->first();

        expect($record->pivot->note)->toBe('Created pivot')
            ->and($record->description)->toBe('Hidden');

        $edit = AutoEditAction::make()->livewire($host)->table($table)->record($record);
        $schema = Schema::make($host)->model($record)->statePath('actionData')->components([
            TextInput::make('description'),
            TextInput::make('note'),
        ]);
        $edit->mount(['schema' => $schema]);

        expect($host->actionData['description'])->toBe('Hidden')
            ->and($host->actionData['note'])->toBe('Created pivot')
            ->and($record->getHidden())->toBe(['description']);

        $edit->data(['name' => 'Updated', 'description' => 'Updated hidden', 'note' => 'Edited pivot'])->call();
        $fresh = $owner->linkedProducts()->first();

        expect($fresh->name)->toBe('Updated')
            ->and($fresh->description)->toBe('Updated hidden')
            ->and($fresh->pivot->note)->toBe('Edited pivot');
    } finally {
        DatabaseSchema::dropIfExists('action_lifecycle_owner_product');
    }
});

it('supports native create-another without replacing the lifecycle', function (): void {
    $host = new ActionLifecycleManager;
    $afterCalled = false;
    $create = AutoCreateAction::make()->livewire($host)->model(ActionLifecycleRecord::class)
        ->arguments(['another' => true])->successNotification(null)
        ->after(function () use (&$afterCalled): void { $afterCalled = true; })
        ->data(['name' => 'Another']);
    $schema = Schema::make($host)->model(ActionLifecycleRecord::class)->statePath('actionData');

    expect(fn () => $create->call(['schema' => $schema]))->toThrow(Halt::class)
        ->and(ActionLifecycleRecord::where('name', 'Another')->exists())->toBeTrue()
        ->and($afterCalled)->toBeTrue()
        ->and($create->getRecord())->toBeNull()
        ->and($schema->getModel())->toBe(ActionLifecycleRecord::class);
});

it('delegates page header actions once and tolerates missing widget keys', function (string $pageClass, string $actionClass): void {
    /** @var class-string<Action> $actionClass */
    $page = new $pageClass;
    $headerActions = (new ReflectionMethod($page, 'getHeaderActions'))->invoke($page);

    expect($headerActions)->toHaveCount(1)
        ->and($headerActions[0])->toBeInstanceOf($actionClass);

    foreach (['getHeaderWidgets', 'getFooterWidgets'] as $method) {
        expect((new ReflectionMethod($page, $method))->invoke($page))->toBe([]);
    }
})->with([
    [ActionLifecycleListPage::class, AutoCreateAction::class],
    [ActionLifecycleViewPage::class, AutoEditAction::class],
]);

it('extends page actions through the native header action hook', function (): void {
    $page = new class extends ActionLifecycleListPage
    {
        protected function getHeaderActions(): array
        {
            return [...parent::getHeaderActions(), Action::make('custom')];
        }
    };

    $actions = (new ReflectionMethod($page, 'getHeaderActions'))->invoke($page);

    expect(array_map(fn (Action $action): string => $action->getName(), $actions))->toBe(['create', 'custom']);
});

it('fills and refreshes view forms without clearing record visibility', function (string $pageClass, bool $intrusive): void {
    $page = new $pageClass;
    $record = (new ActionLifecycleRecord)->forceFill(['name' => 'Visible', 'description' => 'Secret']);
    $page->record = $record;
    $page->fillForTest();

    expect($page->data['description'] ?? null)->toBe($intrusive ? 'Secret' : null)
        ->and($record->getHidden())->toBe(['description']);

    $record->description = 'Refreshed';
    $page->refreshFormData(['description']);

    expect($page->data['description'] ?? null)->toBe($intrusive ? 'Refreshed' : null)
        ->and($record->getHidden())->toBe(['description']);
})->with([
    [ActionLifecycleViewPage::class, true],
    [ActionLifecycleGuardedViewPage::class, false],
]);
