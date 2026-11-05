<?php

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\AutoAction;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleListPage;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ActionLifecycleRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

require_once __DIR__ . '/../Fixtures/ActionLifecycleFixtures.php';

class CoreReadOnlyResource extends ProductResource
{
    protected static bool $readOnly = true;
}

class CoreConfiguredResource extends ProductResource
{
    public static function getActions(): array
    {
        return [
            AutoAction::make('inspect')->showOnTable()->showOnBulkAction(),
            AutoCreateAction::make()->label('Custom create'),
            AutoEditAction::make()->label('Custom edit'),
        ];
    }

    protected static function getEnumDictionary(): array
    {
        return ['status' => ['draft' => 'Dynamic draft']];
    }

    public static function getColumnsOverwrite(): array
    {
        return ['form' => [TextInput::make('name')->label('Inherited name')]];
    }
}

class CorePlacedActionResource extends ProductResource
{
    protected static ?string $model = ActionLifecycleRecord::class;

    public static function getActions(): array
    {
        return [AutoCreateAction::make(), AutoEditAction::make()];
    }
}

class CoreCustomManager extends AutoRelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $relatedResource = ProductResource::class;

    protected static bool $associateAttachActions = false;

    public function getActions(): array
    {
        return [AutoAction::make('manager_bulk')->showOnBulkAction()];
    }
}

class CoreRelationshipOwner extends Owner
{
    protected $table = 'owners';

    public function linkedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'core_owner_product', 'owner_id', 'product_id');
    }

    public function morphProducts(): MorphMany
    {
        return $this->morphMany(Product::class, 'subject');
    }
}

it('assembles resource defaults consistently and lets explicit actions replace them by name', function (): void {
    expect(array_map(fn (Action $action) => $action->getName(), ProductResource::getTableActions()))->toBe(['view', 'restore'])
        ->and(array_map(fn (Action $action) => $action->getName(), ProductResource::getBulkActions()))->toBe(['delete', 'restore', 'forceDelete'])
        ->and(CoreConfiguredResource::getListPageActions())->toHaveCount(1)
        ->and(CoreConfiguredResource::getListPageActions()[0]->getLabel())->toBe('Custom create')
        ->and(CoreConfiguredResource::getViewPageActions()[0]->getLabel())->toBe('Custom edit');

    $tableAction = CoreConfiguredResource::getTableActions()[0];
    $bulkAction = CoreConfiguredResource::getBulkActions()[0];

    expect($tableAction->getName())->toBe('inspect')
        ->and($tableAction->isBulk())->toBeFalse()
        ->and($bulkAction->isBulk())->toBeTrue()
        ->and($bulkAction->canAccessSelectedRecords())->toBeTrue()
        ->and($tableAction)->not->toBe($bulkAction);
});

it('keeps placed native lifecycle callbacks bound to their mounted action', function (): void {
    $host = new ActionLifecycleListPage();
    $create = CorePlacedActionResource::getListPageActions()[0]
        ->livewire($host)->model(ActionLifecycleRecord::class)->data(['name' => 'Placed create']);
    $schema = FilamentSchema::make($host)->model(ActionLifecycleRecord::class);
    $create->call(['schema' => $schema]);
    $record = $create->getRecord();

    $edit = CorePlacedActionResource::getViewPageActions()[0]->livewire($host)->record($record);
    $edit->mount(['schema' => FilamentSchema::make($host)->model($record)]);
    $edit->data(['name' => 'Placed edit'])->call();

    expect($record->fresh()->name)->toBe('Placed edit');
});

it('removes only default mutations from read-only resources and retains inspection filters', function (): void {
    $table = CoreReadOnlyResource::table(Table::make(new ListProducts()));

    expect(CoreReadOnlyResource::getListPageActions())->toBe([])
        ->and(CoreReadOnlyResource::getViewPageActions())->toBe([])
        ->and(CoreReadOnlyResource::getBulkActions())->toBe([])
        ->and(array_keys($table->getFlatActions()))->toBe(['view'])
        ->and($table->getToolbarActions())->toBe([])
        ->and($table->getFilters())->toHaveKey('trashed');
});

it('respects read-only mode on standalone and resource-backed managers', function (bool $backed): void {
    $class = $backed
        ? RelationManagerMounter::makeFromResource(CoreReadOnlyResource::class, 'products')
        : RelationManagerMounter::makeStandalone('products', ['name'], readOnly: true);
    $manager = new $class();
    $manager->ownerRecord = new Owner();
    $manager->pageClass = ListProducts::class;
    $table = Table::make($manager)->relationship(fn () => $manager->getRelationship());

    if ($backed) {
        CoreReadOnlyResource::table($table);
    }

    $manager->table($table);

    expect($table->getHeaderActions())->toBe([])
        ->and($table->getToolbarActions())->toBe([])
        ->and(array_keys($table->getFlatActions()))->toBe(['view']);
})->with([false, true]);

it('allows explicit create actions in read-only mode without duplicating defaults', function (): void {
    $resource = new class extends CoreReadOnlyResource
    {
        public static function getActions(): array
        {
            return [AutoCreateAction::make()];
        }
    };

    expect($resource::getListPageActions())->toHaveCount(1)
        ->and($resource::getListPageActions()[0])->toBeInstanceOf(AutoCreateAction::class);
});

it('inherits dynamic resource dictionaries and tolerates partial overwrite arrays', function (): void {
    $class = RelationManagerMounter::makeFromResource(CoreConfiguredResource::class, 'products', associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = new Owner();
    $schema = $manager->form(FilamentSchema::make($manager)->model(Product::class));
    $fields = collect($schema->getComponents())->keyBy(fn ($field): string => (string) $field->getName());

    expect(CoreConfiguredResource::getColumnsOverwriteMapped('table'))->toBe([])
        ->and($fields['name']->getLabel())->toBe('Inherited name')
        ->and($fields['status'])->toBeInstanceOf(Select::class)
        ->and($fields['status']->getOptions())->toBe(['draft' => 'Dynamic draft']);
});

it('preserves grouped resource actions and includes manager bulk actions', function (): void {
    $manager = new CoreCustomManager();
    $manager->ownerRecord = new Owner();
    $group = ActionGroup::make([ViewAction::make()]);
    $table = ProductResource::table(Table::make($manager)->relationship(fn () => $manager->getRelationship()))
        ->recordActions([$group]);
    $manager->table($table);

    expect($table->getRecordActions())->toBe([$group])
        ->and($table->getAction('manager_bulk')->isBulk())->toBeTrue()
        ->and($table->getAction('manager_bulk')->canAccessSelectedRecords())->toBeTrue();
});

it('handles resource-backed many-to-many managers without inventing an excluded column', function (): void {
    $class = RelationManagerMounter::makeFromResource(ProductResource::class, 'linkedProducts');
    $manager = new $class();
    $manager->ownerRecord = new CoreRelationshipOwner();
    $table = ProductResource::table(Table::make($manager)->relationship(fn () => $manager->getRelationship()));
    $manager->table($table);

    expect($table->getColumns())->toHaveKeys(['id', 'name', 'owner.name'])
        ->and($table->getFlatActions())->toHaveKey('detach');
});

it('excludes both polymorphic owner columns from manager forms and resource-backed displays', function (): void {
    $class = RelationManagerMounter::makeFromResource(ProductResource::class, 'morphProducts', associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = new CoreRelationshipOwner();
    $fields = collect($manager->form(FilamentSchema::make($manager)->model(Product::class))->getComponents())->keyBy(fn ($field): string => (string) $field->getName());
    $table = ProductResource::table(Table::make($manager)->relationship(fn () => $manager->getRelationship()));
    $manager->table($table);

    expect($fields)->not->toHaveKeys(['subject_id', 'subject_type'])
        ->and($table->getColumns())->not->toHaveKey('subject.name');
});

it('generates manager schemas on the inherited related-model connection', function (): void {
    Schema::connection('secondary')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id');
        $table->string('secondary_label');
    });
    $class = RelationManagerMounter::makeStandalone('products', ['secondary_label'], associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = (new Owner())->setConnection('secondary');
    $schema = $manager->form(FilamentSchema::make($manager)->model($manager->getRelationship()->getModel()));
    $fields = collect($schema->getComponents())->keyBy(fn ($field): string => (string) $field->getName());
    $table = $manager->table(Table::make($manager)->relationship(fn () => $manager->getRelationship()));
    $entries = collect($manager->infolist(FilamentSchema::make($manager)->model($manager->getRelationship()->getModel()))->getFlatComponents(withHidden: true))
        ->filter(fn ($component) => $component instanceof TextEntry)->keyBy(fn ($entry): string => (string) $entry->getName());

    expect(array_keys($fields->all()))->toBe(['id', 'secondary_label'])
        ->and($table->getColumns())->toHaveKey('secondary_label')->not->toHaveKey('name')
        ->and($entries)->toHaveKey('secondary_label');
});

it('preserves explicit sort callbacks rather than replacing them with automatic sorting', function (): void {
    $sort = fn (Builder $query): Builder => $query->orderBy('name');
    $table = ProductResource::table(Table::make(new ListProducts())->defaultSort($sort));

    expect($table->getDefaultSortQuery())->toBe($sort);
});

it('uses one form operation group without sharing fields between duplicate containers', function (string $operation): void {
    $schema = ProductResource::form(FilamentSchema::make(new ListProducts())->model(Product::class)->operation($operation));

    expect($schema->getComponents(withHidden: true))->toHaveCount(1)
        ->and($schema->getComponents())->toHaveCount(1);
})->with(['create', 'edit']);

it('retains untyped protected relation-manager extension hooks', function (): void {
    $manager = new class extends AutoRelationManager
    {
        protected static string $relationship = 'products';

        protected function getBaseTableActions(Table $table)
        {
            return [[], [], []];
        }

        protected function getExceptRelationshipColumns()
        {
            return ['owner_id', 'description'];
        }
    };
    $manager->ownerRecord = new Owner();
    $table = $manager->table(Table::make($manager)->relationship(fn () => $manager->getRelationship()));

    expect($table->getColumns())->not->toHaveKeys(['owner.name', 'description']);
});
