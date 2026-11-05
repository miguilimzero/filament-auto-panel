<?php

use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceListRecords;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceViewRecord;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MounterCollisions\ListProducts as OtherListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MounterCollisions\ProductResource as OtherProductResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

it('preserves list view and custom page registrations and their routes', function (): void {
    $panel = Filament::getCurrentPanel() ?? throw new LogicException('No active panel.');
    $registrations = [
        [PageMounter::makeList(ProductResource::class), AutoResourceListRecords::class, '/'],
        [PageMounter::makeView(ProductResource::class), AutoResourceViewRecord::class, '{record}'],
        [PageMounter::makeCustom(ProductResource::class, ListProducts::class, '/custom/{record}'), ListProducts::class, 'custom/{record}'],
    ];

    foreach ($registrations as [$registration, $parent, $route]) {
        expect($registration)->toBeInstanceOf(PageRegistration::class);
        $class = $registration->getPage();

        expect(class_exists($class))->toBeTrue()
            ->and(is_subclass_of($class, $parent))->toBeTrue()
            ->and((new ReflectionClass($class))->isAnonymous())->toBeFalse()
            ->and($class::getResource())->toBe(ProductResource::class)
            ->and($registration->registerRoute($panel)->uri())->toBe($route);
    }
});

it('distinguishes page resource and base classes with identical short names', function (): void {
    $first = PageMounter::makeCustom(ProductResource::class, ListProducts::class, '/first')->getPage();
    $otherResource = PageMounter::makeCustom(OtherProductResource::class, ListProducts::class, '/first')->getPage();
    $otherPage = PageMounter::makeCustom(ProductResource::class, OtherListProducts::class, '/first')->getPage();

    expect(array_unique([$first, $otherResource, $otherPage]))->toHaveCount(3)
        ->and($first::getResource())->toBe(ProductResource::class)
        ->and($otherResource::getResource())->toBe(OtherProductResource::class)
        ->and(get_parent_class($first))->toBe(ListProducts::class)
        ->and(get_parent_class($otherPage))->toBe(OtherListProducts::class);
});

it('reuses page classes without sharing or losing registration routes', function (): void {
    $panel = Filament::getCurrentPanel() ?? throw new LogicException('No active panel.');
    $first = PageMounter::makeCustom(ProductResource::class, ListProducts::class, '/first');
    $second = PageMounter::makeCustom(ProductResource::class, ListProducts::class, '/second');
    $class = $first->getPage();
    $tracked = PageMounter::$mountedClasses;

    try {
        PageMounter::$mountedClasses = [];

        $repeated = PageMounter::makeCustom('\\' . ProductResource::class, '\\' . ListProducts::class, '/third');
        PageMounter::makeCustom(ProductResource::class, ListProducts::class, '/fourth');

        expect($second)->not->toBe($first)
            ->and($second->getPage())->toBe($class)
            ->and($repeated->getPage())->toBe($class)
            ->and(PageMounter::$mountedClasses)->toBe([$class])
            ->and($first->registerRoute($panel)->uri())->toBe('first')
            ->and($second->registerRoute($panel)->uri())->toBe('second')
            ->and($repeated->registerRoute($panel)->uri())->toBe('third');
    } finally {
        PageMounter::$mountedClasses = $tracked;
    }
});

it('distinguishes resource-backed manager resources relationships and optional flags', function (): void {
    $default = RelationManagerMounter::makeFromResource(ProductResource::class, 'products');
    $enabled = RelationManagerMounter::makeFromResource(ProductResource::class, 'products', true);
    $disabled = RelationManagerMounter::makeFromResource(ProductResource::class, 'products', false);
    $otherResource = RelationManagerMounter::makeFromResource(OtherProductResource::class, 'products');
    $otherRelation = RelationManagerMounter::makeFromResource(ProductResource::class, 'archivedProducts');

    expect(array_unique([$default, $enabled, $disabled, $otherResource, $otherRelation]))->toHaveCount(5)
        ->and($default::getRelatedResource())->toBe(ProductResource::class)
        ->and($otherResource::getRelatedResource())->toBe(OtherProductResource::class)
        ->and($default::getRelationshipName())->toBe('products')
        ->and($otherRelation::getRelationshipName())->toBe('archivedProducts')
        ->and((new ReflectionClass($enabled))->getStaticProperties()['associateAttachActions'])->toBeTrue()
        ->and((new ReflectionClass($disabled))->getStaticProperties()['associateAttachActions'])->toBeFalse()
        ->and(RelationManagerMounter::makeFromResource('\\' . ProductResource::class, 'products'))->toBe($default)
        ->and(RelationManagerMounter::makeFromResource(ProductResource::class, 'products', false))->toBe($disabled);
});

it('keys standalone classes by every configuration value without changing earlier mounts', function (array $changes): void {
    $configuration = [
        'relation' => 'products',
        'visibleColumns' => ['name', 'status'],
        'searchableColumns' => ['name' => 'global'],
        'enumDictionary' => ['status' => ['draft' => ['Draft', 'gray']]],
        'recordTitleAttribute' => 'name',
        'associateAttachActions' => true,
        'intrusive' => true,
        'readOnly' => false,
    ];
    $changedConfiguration = array_replace($configuration, $changes);
    $first = RelationManagerMounter::makeStandalone(...$configuration);
    $changed = RelationManagerMounter::makeStandalone(...$changedConfiguration);

    expect($changed)->not->toBe($first)
        ->and(RelationManagerMounter::makeStandalone(...$configuration))->toBe($first)
        ->and(RelationManagerMounter::makeStandalone(...$changedConfiguration))->toBe($changed);

    foreach ([$first => $configuration, $changed => $changedConfiguration] as $class => $expected) {
        $properties = (new ReflectionClass($class))->getStaticProperties();

        foreach ($expected as $key => $value) {
            expect($properties[$key === 'relation' ? 'relationship' : $key])->toBe($value);
        }
    }
})->with([
    'relationship' => [['relation' => 'archivedProducts']],
    'visible columns' => [['visibleColumns' => ['status', 'name']]],
    'searchable columns' => [['searchableColumns' => ['name' => 'both']]],
    'enum dictionary' => [['enumDictionary' => ['status' => ['draft' => ['Not published', 'warning']]]]],
    'record title' => [['recordTitleAttribute' => 'status']],
    'associate and attach actions' => [['associateAttachActions' => false]],
    'intrusive mode' => [['intrusive' => false]],
    'read only mode' => [['readOnly' => true]],
]);

it('does not collapse visible column lists with ambiguous concatenations', function (): void {
    $first = RelationManagerMounter::makeStandalone('products', ['nameN', 'status']);
    $second = RelationManagerMounter::makeStandalone('products', ['name', 'Nstatus']);

    expect($second)->not->toBe($first)
        ->and((new ReflectionClass($first))->getStaticProperties()['visibleColumns'])->toBe(['nameN', 'status'])
        ->and((new ReflectionClass($second))->getStaticProperties()['visibleColumns'])->toBe(['name', 'Nstatus']);
});

it('exports quotes backslashes nested arrays and enum values without changing configuration', function (): void {
    $column = "owner's\\display:name{value}";
    $visible = [$column, 'status', '001'];
    $searchable = [$column => 'both', 'path\\name' => 'individual'];
    $dictionary = [
        'status' => [
            "draft's\\key" => ["Owner's \\ draft: {unpublished}", 'gray'],
            '001' => 'Leading zero',
            7 => ["Line one\nLine two", 'warning'],
        ],
        'metadata' => ['nested' => [false, null, 1.5, MountersTestStatus::Draft]],
    ];
    $title = "owner's\\title";
    $class = RelationManagerMounter::makeStandalone(
        relation: 'products',
        visibleColumns: $visible,
        searchableColumns: $searchable,
        enumDictionary: $dictionary,
        recordTitleAttribute: $title,
        associateAttachActions: false,
        intrusive: false,
        readOnly: true,
    );
    $properties = (new ReflectionClass($class))->getStaticProperties();

    expect($properties['visibleColumns'])->toBe($visible)
        ->and($properties['searchableColumns'])->toBe($searchable)
        ->and((new ReflectionProperty($class, 'visibleColumns'))->isPublic())->toBeTrue()
        ->and((new ReflectionProperty($class, 'searchableColumns'))->isPublic())->toBeTrue()
        ->and($properties['enumDictionary'])->toBe($dictionary)
        ->and($properties['recordTitleAttribute'])->toBe($title)
        ->and($properties['associateAttachActions'])->toBeFalse()
        ->and($properties['intrusive'])->toBeFalse()
        ->and($properties['readOnly'])->toBeTrue();
});

it('inherits omitted optional values and includes the manager base class in its identity', function (): void {
    $default = RelationManagerMounter::makeStandalone('products', ['name']);
    $inherited = MountersTestConfiguredMounter::makeStandalone('products', ['name']);
    $explicit = MountersTestConfiguredMounter::makeStandalone('products', ['name'], [], [], '', true, true, false);
    $fromResource = MountersTestConfiguredMounter::makeFromResource(ProductResource::class, 'products');
    $properties = (new ReflectionClass($inherited))->getStaticProperties();
    $overrides = (new ReflectionClass($explicit))->getStaticProperties();

    expect($inherited)->not->toBe($default)
        ->and(get_parent_class($inherited))->toBe(MountersTestConfiguredManager::class)
        ->and($properties['recordTitleAttribute'])->toBe('default_title')
        ->and($properties['associateAttachActions'])->toBeFalse()
        ->and($properties['intrusive'])->toBeFalse()
        ->and($properties['readOnly'])->toBeTrue()
        ->and($overrides['recordTitleAttribute'])->toBe('')
        ->and($overrides['associateAttachActions'])->toBeTrue()
        ->and($overrides['intrusive'])->toBeTrue()
        ->and($overrides['readOnly'])->toBeFalse()
        ->and((new ReflectionClass($fromResource))->getStaticProperties()['associateAttachActions'])->toBeFalse();
});

it('does not expose the deprecated standalone manager alias', function (): void {
    expect(method_exists(RelationManagerMounter::class, 'make'))->toBeFalse();
});

it('reuses manager classes even after tracking is cleared without mixing standalone and resource modes', function (): void {
    $resource = RelationManagerMounter::makeFromResource(ProductResource::class, 'products', false);
    $standalone = RelationManagerMounter::makeStandalone('products', ['name'], associateAttachActions: false);
    $cache = new ReflectionProperty(RelationManagerMounter::class, 'mountedClasses');
    $tracked = $cache->getValue();

    try {
        $cache->setValue(null, []);

        expect(RelationManagerMounter::makeFromResource(ProductResource::class, 'products', false))->toBe($resource)
            ->and(RelationManagerMounter::makeStandalone('products', ['name'], associateAttachActions: false))->toBe($standalone)
            ->and(RelationManagerMounter::makeStandalone('products', ['name'], associateAttachActions: false))->toBe($standalone)
            ->and($resource)->not->toBe($standalone)
            ->and($resource::getRelatedResource())->toBe(ProductResource::class)
            ->and($standalone::getRelatedResource())->toBeNull()
            ->and($cache->getValue())->toBe([$resource, $standalone]);
    } finally {
        $cache->setValue(null, $tracked);
    }
});

it('returns named manager classes usable with real relationships and Livewire resolution', function (): void {
    $classes = [
        PageMounter::makeList(ProductResource::class)->getPage(),
        RelationManagerMounter::makeFromResource(ProductResource::class, 'products'),
        RelationManagerMounter::makeStandalone('products', ['name']),
    ];
    $owner = Owner::create(['name' => 'Mounter owner']);

    foreach ($classes as $index => $class) {
        Livewire::component('mounter-test-' . $index, $class);
        $component = Livewire::new('mounter-test-' . $index);

        expect($component::class)->toBe($class)
            ->and((new ReflectionClass($class))->isAnonymous())->toBeFalse()
            ->and(Livewire::new($component->getName())::class)->toBe($class);

        if ($component instanceof AutoRelationManager) {
            $component->ownerRecord = $owner;
            $component->pageClass = ListProducts::class;

            expect($component->getRelationship()->getRelated())->toBeInstanceOf(Product::class);
        }
    }
});

it('rejects malformed relationship identifiers before generating classes', function (string $relation): void {
    expect(fn () => RelationManagerMounter::makeStandalone($relation, ['name']))
        ->toThrow(InvalidArgumentException::class, 'must be a valid method name')
        ->and(fn () => RelationManagerMounter::makeFromResource(ProductResource::class, $relation))
        ->toThrow(InvalidArgumentException::class, 'must be a valid method name');
})->with(['', 'products.items', '123products', "products'; throw new Exception; //", 'products\\items']);

it('rejects invalid class references before generating classes', function (): void {
    expect(fn () => PageMounter::makeList('MissingMounterResource'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => PageMounter::makeList(Product::class))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => PageMounter::makeCustom(ProductResource::class, Product::class, '/custom'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => PageMounter::makeCustom(ProductResource::class, MountersTestFinalPage::class, '/custom'))
        ->toThrow(InvalidArgumentException::class, 'must be concrete and non-final')
        ->and(fn () => RelationManagerMounter::makeFromResource(Product::class, 'products'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => MountersTestInvalidMounter::makeStandalone('products', ['name']))
        ->toThrow(InvalidArgumentException::class);
});

enum MountersTestStatus: string
{
    case Draft = "draft's\\value";
}

class MountersTestConfiguredManager extends AutoRelationManager
{
    protected static ?string $recordTitleAttribute = 'default_title';

    protected static bool $associateAttachActions = false;

    protected static bool $intrusive = false;

    protected static bool $readOnly = true;
}

class MountersTestConfiguredMounter extends RelationManagerMounter
{
    protected static function getRelationManagerClass(): string
    {
        return MountersTestConfiguredManager::class;
    }
}

class MountersTestInvalidMounter extends RelationManagerMounter
{
    protected static function getRelationManagerClass(): string
    {
        return Product::class;
    }
}

final class MountersTestFinalPage extends ListProducts
{
}
