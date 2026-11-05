<?php

namespace Miguilim\FilamentAutoPanel\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Schemas\Components\StateCasts\NumberStateCast;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

class QueryBuilderFilteringProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(QueryBuilderFilteringOwner::class, 'owner_id');
    }
}

class QueryBuilderFilteringOwner extends Owner
{
    protected $table = 'owners';

    public function products(): HasMany
    {
        return $this->hasMany(QueryBuilderFilteringProduct::class, 'owner_id');
    }
}

class QueryBuilderFilteringScopedProduct extends QueryBuilderFilteringProduct
{
    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query): Builder => $query->where('products.is_active', true));
    }
}

class QueryBuilderFilteringTimestampProduct extends QueryBuilderFilteringProduct
{
    protected function casts(): array
    {
        return ['published_at' => 'timestamp'];
    }
}

class QueryBuilderFilteringResource extends ProductResource
{
    protected static ?string $model = QueryBuilderFilteringProduct::class;

    protected static string $listPage = QueryBuilderFilteringListProducts::class;

    protected static array $enumDictionary = [
        'status' => [
            'draft' => ['Draft article', 'warning'],
            'published' => ['Published article', 'success'],
            'archived' => ['Archived article', 'gray'],
        ],
    ];

    public static function getPages(): array
    {
        return ['index' => static::$listPage::route('/')];
    }

    public static function getFilters(): array
    {
        return [Filter::make('stocked')->query(fn (Builder $query): Builder => $query->where('products.quantity', '>', 0))];
    }
}

class QueryBuilderFilteringListProducts extends ListProducts
{
    protected static string $resource = QueryBuilderFilteringResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters(false);
    }
}

class QueryBuilderFilteringPlainResource extends QueryBuilderFilteringResource
{
    protected static array $enumDictionary = [];

    protected static string $listPage = QueryBuilderFilteringPlainListProducts::class;
}

class QueryBuilderFilteringPlainListProducts extends QueryBuilderFilteringListProducts
{
    protected static string $resource = QueryBuilderFilteringPlainResource::class;
}

class QueryBuilderFilteringScopedResource extends QueryBuilderFilteringResource
{
    protected static ?string $model = QueryBuilderFilteringScopedProduct::class;

    protected static string $listPage = QueryBuilderFilteringScopedListProducts::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('products.quantity', '>=', 10);
    }
}

class QueryBuilderFilteringScopedListProducts extends QueryBuilderFilteringListProducts
{
    protected static string $resource = QueryBuilderFilteringScopedResource::class;
}

class QueryBuilderFilteringTimestampResource extends QueryBuilderFilteringResource
{
    protected static ?string $model = QueryBuilderFilteringTimestampProduct::class;

    protected static string $listPage = QueryBuilderFilteringTimestampListProducts::class;
}

class QueryBuilderFilteringTimestampListProducts extends QueryBuilderFilteringListProducts
{
    protected static string $resource = QueryBuilderFilteringTimestampResource::class;
}

class QueryBuilderFilteringCustomResource extends QueryBuilderFilteringResource
{
    protected static string $listPage = QueryBuilderFilteringCustomListProducts::class;

    public static function getFilters(): array
    {
        return [
            ...parent::getFilters(),
            QueryBuilder::make('queryBuilder')->label('Custom product rules')
                ->constraints([TextConstraint::make('customName')->attribute('name')]),
        ];
    }
}

class QueryBuilderFilteringCustomListProducts extends QueryBuilderFilteringListProducts
{
    protected static string $resource = QueryBuilderFilteringCustomResource::class;
}

class QueryBuilderFilteringManager extends AutoRelationManager
{
    protected static string $relationship = 'products';

    protected static bool $associateAttachActions = false;

    protected static ?bool $isLazyLoaded = false;

    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters(false);
    }

    public function getFilters(): array
    {
        return [Filter::make('stocked')->query(fn (Builder $query): Builder => $query->where('products.quantity', '>', 0))];
    }
}

class QueryBuilderFilteringResourceManager extends QueryBuilderFilteringManager
{
    protected static ?string $relatedResource = QueryBuilderFilteringResource::class;
}

beforeEach(function (): void {
    $resources = [
        QueryBuilderFilteringResource::class,
        QueryBuilderFilteringPlainResource::class,
        QueryBuilderFilteringScopedResource::class,
        QueryBuilderFilteringTimestampResource::class,
        QueryBuilderFilteringCustomResource::class,
    ];
    $panel = Panel::make()->id('query-builder-filtering')->path('query-builder-filtering')->resources($resources);
    Filament::setCurrentPanel($panel);

    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(function () use ($panel, $resources): void {
            foreach ($resources as $resource) {
                $resource::registerRoutes($panel);
            }
        });
    Route::getRoutes()->refreshNameLookups();
});

function queryBuilderFilteringProduct(array $attributes = []): QueryBuilderFilteringProduct
{
    return QueryBuilderFilteringProduct::create([
        'name' => 'Ordinary product',
        'price' => '10.00',
        ...$attributes,
    ]);
}

/** @return array{QueryBuilderFilteringProduct, QueryBuilderFilteringProduct, QueryBuilderFilteringProduct} */
function queryBuilderFilteringBigIntegers(string $column): array
{
    $records = [];

    foreach (['9007199254740992', '9007199254740993', '9007199254740994'] as $index => $value) {
        $records[] = queryBuilderFilteringProduct([
            'id' => $column === 'id' ? $value : (string) ($index + 1),
            'name' => 'BIGINT ' . $value,
            ...($column === 'subject_id' ? ['subject_type' => QueryBuilderFilteringOwner::class, 'subject_id' => $value] : []),
        ]);
    }

    return $records;
}

function queryBuilderFilteringRule(string $constraint, string $operator, array $settings = []): array
{
    return ['type' => $constraint, 'data' => ['operator' => $operator, 'settings' => $settings]];
}

function queryBuilderFilteringRules(array ...$rules): array
{
    $state = ['rules' => []];

    // Native builders and repeaters identify hydrated children by string keys.
    foreach ($rules as $index => $rule) {
        $state['rules']['r' . ($index + 1)] = $rule;
    }

    return $state;
}

function queryBuilderFilteringOr(array ...$groups): array
{
    $state = ['type' => 'or', 'data' => ['groups' => []]];

    foreach ($groups as $index => $group) {
        $state['data']['groups']['g' . ($index + 1)] = $group;
    }

    return $state;
}

function queryBuilderFilteringPage(string $page = QueryBuilderFilteringListProducts::class): Testable
{
    return Livewire::test($page)->assertSuccessful();
}

it('mounts the automatic native constraint schema without treating json or morph keys as relationships', function (): void {
    $page = queryBuilderFilteringPage(QueryBuilderFilteringPlainListProducts::class)
        ->assertTableFilterExists('queryBuilder', fn ($filter): bool => $filter instanceof QueryBuilder)
        ->assertTableFilterExists('stocked')
        ->assertTableFilterExists('trashed', fn ($filter): bool => $filter instanceof TrashedFilter);
    $filter = $page->instance()->getTable()->getFilter('queryBuilder');

    foreach (['name', 'description', 'status', 'subject_type'] as $name) {
        expect($filter->getConstraint($name))->toBeInstanceOf(TextConstraint::class);
    }

    foreach (['id', 'quantity', 'price', 'subject_id'] as $name) {
        expect($filter->getConstraint($name))->toBeInstanceOf(NumberConstraint::class);
    }

    expect($filter->getConstraint('is_active'))->toBeInstanceOf(BooleanConstraint::class)
        ->and($filter->getConstraint('published_on'))->toBeInstanceOf(DateConstraint::class)
        ->and($filter->getConstraint('published_on')->hasTime())->toBeFalse()
        ->and($filter->getConstraint('owner'))->toBeInstanceOf(RelationshipConstraint::class)
        ->and($filter->getConstraint('owner_id'))->toBeNull()
        ->and($filter->getConstraint('metadata'))->toBeNull();

    foreach (['published_at', 'created_at', 'updated_at', 'deleted_at'] as $name) {
        expect($filter->getConstraint($name))->toBeInstanceOf(DateConstraint::class)
            ->and($filter->getConstraint($name)->hasTime())->toBeTrue();
    }
});

it('filters text contains and its inverse after a Livewire state update', function (): void {
    $match = queryBuilderFilteringProduct(['name' => 'A foo product']);
    $other = queryBuilderFilteringProduct(['name' => 'A bar product']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'contains', ['text' => 'foo'])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'contains.inverse', ['text' => 'foo'])))
        ->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$match])->assertCountTableRecords(1);
});

it('distinguishes filled nullable descriptions from null and empty strings', function (): void {
    $filled = queryBuilderFilteringProduct(['description' => 'Details']);
    $null = queryBuilderFilteringProduct(['description' => null]);
    $empty = queryBuilderFilteringProduct(['description' => '']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('description', 'isFilled')))
        ->assertCanSeeTableRecords([$filled])->assertCanNotSeeTableRecords([$null, $empty])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('description', 'isFilled.inverse')))
        ->assertCanSeeTableRecords([$null, $empty])->assertCanNotSeeTableRecords([$filled])->assertCountTableRecords(2);
});

it('filters a status without a dictionary as ordinary text', function (): void {
    $match = queryBuilderFilteringProduct(['status' => 'unlisted-status']);
    $other = queryBuilderFilteringProduct(['status' => 'draft']);

    queryBuilderFilteringPage(QueryBuilderFilteringPlainListProducts::class)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('status', 'equals', ['text' => 'unlisted-status'])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other])->assertCountTableRecords(1);
});

it('filters the numeric primary key rather than a related record key', function (): void {
    $match = queryBuilderFilteringProduct();
    $other = queryBuilderFilteringProduct();

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('id', 'equals', ['number' => (string) $match->id, 'aggregate' => null])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other])->assertCountTableRecords(1);
});

it('distinguishes adjacent BIGINT strings above floating point precision in native equality and inverse rules', function (string $column): void {
    [$lower, $upper, $higher] = queryBuilderFilteringBigIntegers($column);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'equals', ['number' => '9007199254740993'])))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740993')
        ->assertCanSeeTableRecords([$upper])->assertCanNotSeeTableRecords([$lower, $higher])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'equals', ['number' => '9007199254740992'])))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740992')
        ->assertCanSeeTableRecords([$lower])->assertCanNotSeeTableRecords([$upper, $higher])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'equals.inverse', ['number' => '9007199254740993'])))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740993')
        ->assertCanSeeTableRecords([$lower, $higher])->assertCanNotSeeTableRecords([$upper])->assertCountTableRecords(2);
})->with(['primary key' => ['id'], 'raw morph key' => ['subject_id']]);

it('compares exact BIGINT range boundaries and their inverses through native Livewire rules', function (string $column): void {
    [$lower, $upper, $higher] = queryBuilderFilteringBigIntegers($column);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'isMin', ['number' => '9007199254740993'])))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740993')
        ->assertCanSeeTableRecords([$upper, $higher])->assertCanNotSeeTableRecords([$lower])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'isMin.inverse', ['number' => '9007199254740993'])))
        ->assertCanSeeTableRecords([$lower])->assertCanNotSeeTableRecords([$upper, $higher])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'isMax', ['number' => '9007199254740992'])))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740992')
        ->assertCanSeeTableRecords([$lower])->assertCanNotSeeTableRecords([$upper, $higher])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule($column, 'isMax.inverse', ['number' => '9007199254740992'])))
        ->assertCanSeeTableRecords([$upper, $higher])->assertCanNotSeeTableRecords([$lower])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule($column, 'isMin', ['number' => '9007199254740993']),
            queryBuilderFilteringRule($column, 'isMax', ['number' => '9007199254740993']),
        ))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740993')
        ->assertSet('tableFilters.queryBuilder.rules.r2.data.settings.number', '9007199254740993')
        ->assertCanSeeTableRecords([$upper])->assertCanNotSeeTableRecords([$lower, $higher])->assertCountTableRecords(1);
})->with(['primary key' => ['id'], 'raw morph key' => ['subject_id']]);

it('keeps all integer comparison inputs as required integer-string text fields without aggregate selectors or number casts', function (): void {
    $page = queryBuilderFilteringPage();
    $constraint = $page->instance()->getTable()->getFilter('queryBuilder')->getConstraint('id');
    expect($constraint)->toBeInstanceOf(NumberConstraint::class)
        ->and($constraint->isInteger())->toBeTrue();

    foreach (['equals', 'isMin', 'isMax'] as $operator) {
        $page->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('id', $operator, ['number' => '9007199254740993'])))
            ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '9007199254740993');
        $fields = collect($page->instance()->getTableFiltersForm()->getFlatFields(withHidden: true));
        $numberFields = $fields->filter(fn ($field): bool => $field->getName() === 'number');
        expect($numberFields)->toHaveCount(1)
            ->and($fields->contains(fn ($field): bool => $field->getName() === 'aggregate'))->toBeFalse();
        $number = $numberFields->first();

        expect($number)->toBeInstanceOf(TextInput::class)
            ->and($number->isRequired())->toBeTrue()
            ->and($number->getType())->toBe('text')
            ->and($number->getInputMode())->toBe('numeric')
            ->and($number->isNumeric())->toBeFalse()
            ->and($number->getState())->toBe('9007199254740993')
            ->and(collect($number->getStateCasts())->contains(fn ($cast): bool => $cast instanceof NumberStateCast))->toBeFalse();

        $regexRules = array_values(array_filter($number->getValidationRules(), fn ($rule): bool => is_string($rule) && str_starts_with($rule, 'regex:')));
        expect($regexRules)->toHaveCount(1);
        $pattern = substr($regexRules[0], strlen('regex:'));

        foreach (['9007199254740992', '9007199254740993', '-2', '0'] as $valid) {
            expect(preg_match($pattern, $valid))->toBe(1);
        }

        foreach (['12.34', '9e15', 'not-a-number'] as $invalid) {
            expect(preg_match($pattern, $invalid))->toBe(0);
        }
    }
});

it('filters signed negative integer strings with native equality inverse and range rules', function (): void {
    $lower = queryBuilderFilteringProduct(['quantity' => -3]);
    $upper = queryBuilderFilteringProduct(['quantity' => -2]);
    $zero = queryBuilderFilteringProduct(['quantity' => 0]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('quantity', 'equals', ['number' => '-2'])))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '-2')
        ->assertCanSeeTableRecords([$upper])->assertCanNotSeeTableRecords([$lower, $zero])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('quantity', 'equals.inverse', ['number' => '-2'])))
        ->assertCanSeeTableRecords([$lower, $zero])->assertCanNotSeeTableRecords([$upper])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('quantity', 'isMin', ['number' => '-3']),
            queryBuilderFilteringRule('quantity', 'isMax', ['number' => '-2']),
        ))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '-3')
        ->assertSet('tableFilters.queryBuilder.rules.r2.data.settings.number', '-2')
        ->assertCanSeeTableRecords([$lower, $upper])->assertCanNotSeeTableRecords([$zero])->assertCountTableRecords(2);
});

it('combines inclusive integer bounds including negative and zero quantities', function (): void {
    $below = queryBuilderFilteringProduct(['quantity' => -3]);
    $lower = queryBuilderFilteringProduct(['quantity' => -2]);
    $zero = queryBuilderFilteringProduct(['quantity' => 0]);
    $above = queryBuilderFilteringProduct(['quantity' => 1]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('quantity', 'isMin', ['number' => '-2', 'aggregate' => null]),
            queryBuilderFilteringRule('quantity', 'isMax', ['number' => '0', 'aggregate' => null]),
        ))
        ->assertCanSeeTableRecords([$lower, $zero])->assertCanNotSeeTableRecords([$below, $above])->assertCountTableRecords(2);
});

it('compares decimal equality without truncating fractional prices', function (): void {
    $below = queryBuilderFilteringProduct(['price' => '12.33']);
    $match = queryBuilderFilteringProduct(['price' => '12.34']);
    $above = queryBuilderFilteringProduct(['price' => '12.35']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('price', 'equals', ['number' => '12.34', 'aggregate' => null])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$below, $above])->assertCountTableRecords(1);
});

it('keeps both decimal range boundaries inclusive', function (): void {
    $below = queryBuilderFilteringProduct(['price' => '19.98']);
    $lower = queryBuilderFilteringProduct(['price' => '19.99']);
    $upper = queryBuilderFilteringProduct(['price' => '20.01']);
    $above = queryBuilderFilteringProduct(['price' => '20.02']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('price', 'isMin', ['number' => '19.99', 'aggregate' => null]),
            queryBuilderFilteringRule('price', 'isMax', ['number' => '20.01', 'aggregate' => null]),
        ))
        ->assertCanSeeTableRecords([$lower, $upper])->assertCanNotSeeTableRecords([$below, $above])->assertCountTableRecords(2);
});

it('switches native boolean true and false rules without stale results', function (): void {
    $active = queryBuilderFilteringProduct(['is_active' => true]);
    $inactive = queryBuilderFilteringProduct(['is_active' => false]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('is_active', 'isTrue')))
        ->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$inactive])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('is_active', 'isTrue.inverse')))
        ->assertCanSeeTableRecords([$inactive])->assertCanNotSeeTableRecords([$active])->assertCountTableRecords(1);
});

it('filters date equality and nullable dates through native date rules', function (): void {
    $match = queryBuilderFilteringProduct(['published_on' => '2025-05-10']);
    $other = queryBuilderFilteringProduct(['published_on' => '2025-05-11']);
    $null = queryBuilderFilteringProduct(['published_on' => null]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('published_on', 'isDate', ['date' => '2025-05-10'])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other, $null])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('published_on', 'isFilled.inverse')))
        ->assertCanSeeTableRecords([$null])->assertCanNotSeeTableRecords([$match, $other])->assertCountTableRecords(1);
});

it('uses the inclusive native absolute after boundary for date columns', function (): void {
    $before = queryBuilderFilteringProduct(['published_on' => '2025-05-09']);
    $boundary = queryBuilderFilteringProduct(['published_on' => '2025-05-10']);
    $after = queryBuilderFilteringProduct(['published_on' => '2025-05-11']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('published_on', 'isAfter', ['mode' => 'absolute', 'date' => '2025-05-10'])))
        ->assertCanSeeTableRecords([$boundary, $after])->assertCanNotSeeTableRecords([$before])->assertCountTableRecords(2);
});

it('preserves time when comparing datetime values on the same day', function (): void {
    $before = queryBuilderFilteringProduct(['published_at' => '2025-05-10 11:59:59']);
    $boundary = queryBuilderFilteringProduct(['published_at' => '2025-05-10 12:00:00']);
    $after = queryBuilderFilteringProduct(['published_at' => '2025-05-10 12:00:01']);
    $null = queryBuilderFilteringProduct(['published_at' => null]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('published_at', 'isAfter', ['mode' => 'absolute', 'date' => '2025-05-10 12:00:00'])))
        ->assertCanSeeTableRecords([$boundary, $after])->assertCanNotSeeTableRecords([$before, $null])->assertCountTableRecords(2);
});

it('keeps a physically temporal timestamp-cast column time-aware during native date filtering', function (): void {
    $before = queryBuilderFilteringProduct(['published_at' => '2025-05-10 11:59:59']);
    $boundary = queryBuilderFilteringProduct(['published_at' => '2025-05-10 12:00:00']);
    $after = queryBuilderFilteringProduct(['published_at' => '2025-05-10 12:00:01']);
    $tomorrow = queryBuilderFilteringProduct(['published_at' => '2025-05-11 12:00:00']);
    $null = queryBuilderFilteringProduct(['published_at' => null]);
    $page = queryBuilderFilteringPage(QueryBuilderFilteringTimestampListProducts::class);
    $constraint = $page->instance()->getTable()->getFilter('queryBuilder')->getConstraint('published_at');

    expect((new QueryBuilderFilteringTimestampProduct())->getCasts()['published_at'])->toBe('timestamp')
        ->and($constraint)->toBeInstanceOf(DateConstraint::class)
        ->and($constraint->hasTime())->toBeTrue();

    $page->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('published_at', 'isAfter', ['mode' => 'absolute', 'date' => '2025-05-10 12:00:00']),
            queryBuilderFilteringRule('published_at', 'isDate', ['date' => '2025-05-10']),
        ))
        ->assertCanSeeTableRecords([$boundary, $after])->assertCanNotSeeTableRecords([$before, $tomorrow, $null])->assertCountTableRecords(2);
});

it('generates time-aware rules for both created and updated timestamps', function (): void {
    $before = queryBuilderFilteringProduct(['created_at' => '2025-05-10 09:00:00', 'updated_at' => '2025-05-10 13:00:00']);
    $match = queryBuilderFilteringProduct(['created_at' => '2025-05-10 12:00:00', 'updated_at' => '2025-05-10 13:00:00']);
    $late = queryBuilderFilteringProduct(['created_at' => '2025-05-10 12:00:00', 'updated_at' => '2025-05-10 15:00:00']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('created_at', 'isAfter', ['mode' => 'absolute', 'date' => '2025-05-10 12:00:00']),
            queryBuilderFilteringRule('updated_at', 'isBefore', ['mode' => 'absolute', 'date' => '2025-05-10 14:00:00']),
        ))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$before, $late])->assertCountTableRecords(1);
});

it('filters dictionary values with multiple selection and its inverse rather than labels', function (): void {
    $draft = queryBuilderFilteringProduct(['status' => 'draft']);
    $published = queryBuilderFilteringProduct(['status' => 'published']);
    $archived = queryBuilderFilteringProduct(['status' => 'archived']);
    $page = queryBuilderFilteringPage();
    $constraint = $page->instance()->getTable()->getFilter('queryBuilder')->getConstraint('status');
    expect($constraint)->toBeInstanceOf(SelectConstraint::class)
        ->and($constraint->isMultiple())->toBeTrue();

    $page->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('status', 'is', ['values' => ['draft', 'published']])))
        ->assertCanSeeTableRecords([$draft, $published])->assertCanNotSeeTableRecords([$archived])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('status', 'is.inverse', ['values' => ['draft', 'published']])))
        ->assertCanSeeTableRecords([$archived])->assertCanNotSeeTableRecords([$draft, $published])->assertCountTableRecords(1);
});

it('filters multiple belongs-to keys using the native searchable singular relationship operator', function (): void {
    $first = QueryBuilderFilteringOwner::create(['name' => 'First owner']);
    $second = QueryBuilderFilteringOwner::create(['name' => 'Second owner']);
    $third = QueryBuilderFilteringOwner::create(['name' => 'Third owner']);
    $one = queryBuilderFilteringProduct(['owner_id' => $first->id]);
    $two = queryBuilderFilteringProduct(['owner_id' => $second->id]);
    $other = queryBuilderFilteringProduct(['owner_id' => $third->id]);
    $unowned = queryBuilderFilteringProduct();
    $page = queryBuilderFilteringPage();
    $constraint = $page->instance()->getTable()->getFilter('queryBuilder')->getConstraint('owner');
    $operator = $constraint->getOperator('isRelatedTo');
    expect($constraint->isMultiple())->toBeFalse()
        ->and($operator->isMultiple())->toBeTrue()
        ->and($operator->isSearchable())->toBeTrue();

    $page->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('owner', 'isRelatedTo', ['value' => [(string) $first->id, (string) $second->id]])))
        ->assertCanSeeTableRecords([$one, $two])->assertCanNotSeeTableRecords([$other, $unowned])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('owner', 'isRelatedTo.inverse', ['value' => [(string) $first->id, (string) $second->id]])))
        ->assertCanSeeTableRecords([$other, $unowned])->assertCanNotSeeTableRecords([$one, $two])->assertCountTableRecords(2);
});

it('offers native empty and nonempty relationship rules for a nullable foreign key', function (): void {
    $owner = QueryBuilderFilteringOwner::create(['name' => 'Owner']);
    $owned = queryBuilderFilteringProduct(['owner_id' => $owner->id]);
    $unowned = queryBuilderFilteringProduct(['owner_id' => null]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('owner', 'isEmpty')))
        ->assertCanSeeTableRecords([$unowned])->assertCanNotSeeTableRecords([$owned])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('owner', 'isEmpty.inverse')))
        ->assertCanSeeTableRecords([$owned])->assertCanNotSeeTableRecords([$unowned])->assertCountTableRecords(1);
});

it('ANDs independent text boolean and numeric rules at the root', function (): void {
    $match = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 5]);
    $wrongName = queryBuilderFilteringProduct(['name' => 'bar', 'is_active' => true, 'quantity' => 5]);
    $inactive = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => false, 'quantity' => 5]);
    $low = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 4]);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('name', 'contains', ['text' => 'foo']),
            queryBuilderFilteringRule('is_active', 'isTrue'),
            queryBuilderFilteringRule('quantity', 'isMin', ['number' => '5', 'aggregate' => null]),
        ))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$wrongName, $inactive, $low])->assertCountTableRecords(1);
});

it('preserves nested AND and OR group boundaries through native schema hydration', function (): void {
    $first = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'status' => 'draft']);
    $second = queryBuilderFilteringProduct(['name' => 'bar', 'quantity' => 5, 'is_active' => true, 'status' => 'published']);
    $third = queryBuilderFilteringProduct(['name' => 'bar', 'quantity' => 5, 'is_active' => false, 'status' => 'draft']);
    $low = queryBuilderFilteringProduct(['name' => 'bar', 'quantity' => 4, 'status' => 'draft']);
    $wrongStatus = queryBuilderFilteringProduct(['name' => 'bar', 'quantity' => 5, 'is_active' => true, 'status' => 'draft']);
    $inactiveFoo = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => false, 'status' => 'published']);

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringOr(
            queryBuilderFilteringRules(
                queryBuilderFilteringRule('name', 'equals', ['text' => 'foo']),
                queryBuilderFilteringRule('is_active', 'isTrue'),
            ),
            queryBuilderFilteringRules(
                queryBuilderFilteringRule('quantity', 'isMin', ['number' => '5', 'aggregate' => null]),
                queryBuilderFilteringOr(
                    queryBuilderFilteringRules(queryBuilderFilteringRule('status', 'is', ['values' => ['published']]), queryBuilderFilteringRule('is_active', 'isTrue')),
                    queryBuilderFilteringRules(queryBuilderFilteringRule('status', 'is', ['values' => ['draft']]), queryBuilderFilteringRule('is_active', 'isTrue.inverse')),
                ),
            ),
        )))
        ->assertCanSeeTableRecords([$first, $second, $third])
        ->assertCanNotSeeTableRecords([$low, $wrongStatus, $inactiveFoo])->assertCountTableRecords(3);
});

it('keeps global scopes base query restrictions and custom filters outside OR groups', function (): void {
    $match = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 10, 'price' => '15.00']);
    $otherBranch = queryBuilderFilteringProduct(['name' => 'bar', 'is_active' => true, 'quantity' => 10, 'price' => '25.00']);
    $inactive = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => false, 'quantity' => 10]);
    $low = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 9]);
    $expensive = queryBuilderFilteringProduct(['name' => 'bar', 'is_active' => true, 'quantity' => 10, 'price' => '30.00']);

    queryBuilderFilteringPage(QueryBuilderFilteringScopedListProducts::class)
        ->filterTable('stocked')
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringOr(
            queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'equals', ['text' => 'foo'])),
            queryBuilderFilteringRules(queryBuilderFilteringRule('price', 'equals', ['number' => '25.00', 'aggregate' => null])),
        )))
        ->assertCanSeeTableRecords([$match, $otherBranch])->assertCanNotSeeTableRecords([$inactive, $low, $expensive])->assertCountTableRecords(2);
});

it('ignores invalid native rule settings without dropping valid siblings or the base scope', function (): void {
    $match = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 10]);
    $wrongName = queryBuilderFilteringProduct(['name' => 'bar', 'is_active' => true, 'quantity' => 10]);
    $inactive = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => false, 'quantity' => 10]);
    $low = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 9]);

    queryBuilderFilteringPage(QueryBuilderFilteringScopedListProducts::class)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(
            queryBuilderFilteringRule('quantity', 'isMin', ['number' => 'not-a-number', 'aggregate' => null]),
            queryBuilderFilteringRule('published_on', 'isDate', ['date' => null]),
            queryBuilderFilteringRule('name', 'contains', ['text' => 'foo']),
        ))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$wrongName, $inactive, $low])->assertCountTableRecords(1);
});

it('replaces and clears native rules without losing custom filters or existing scopes', function (): void {
    $foo = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 10]);
    $bar = queryBuilderFilteringProduct(['name' => 'bar', 'is_active' => true, 'quantity' => 10]);
    $inactive = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => false, 'quantity' => 10]);
    $low = queryBuilderFilteringProduct(['name' => 'foo', 'is_active' => true, 'quantity' => 0]);

    queryBuilderFilteringPage(QueryBuilderFilteringScopedListProducts::class)
        ->filterTable('stocked')
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'equals', ['text' => 'foo'])))
        ->assertCanSeeTableRecords([$foo])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'equals', ['text' => 'bar'])))
        ->assertCanSeeTableRecords([$bar])->assertCanNotSeeTableRecords([$foo])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules())
        ->assertCanSeeTableRecords([$foo, $bar])->assertCanNotSeeTableRecords([$inactive, $low])->assertCountTableRecords(2)
        ->assertSet('tableFilters.stocked.isActive', true)
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'equals', ['text' => 'foo'])))
        ->removeTableFilter('queryBuilder')
        ->assertCanSeeTableRecords([$foo, $bar])->assertCanNotSeeTableRecords([$inactive, $low])->assertCountTableRecords(2)
        ->assertSet('tableFilters.stocked.isActive', true);
});

it('combines rules with default with-trashed and only-trashed native filtering', function (): void {
    $live = queryBuilderFilteringProduct(['name' => 'foo live']);
    $deleted = queryBuilderFilteringProduct(['name' => 'foo deleted']);
    $other = queryBuilderFilteringProduct(['name' => 'bar deleted']);
    $deleted->delete();
    $other->delete();

    queryBuilderFilteringPage()
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'contains', ['text' => 'foo'])))
        ->assertCanSeeTableRecords([$live])->assertCanNotSeeTableRecords([$deleted, $other])->assertCountTableRecords(1)
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$live, $deleted])->assertCanNotSeeTableRecords([$other])->assertCountTableRecords(2)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$deleted])->assertCanNotSeeTableRecords([$live, $other])->assertCountTableRecords(1)
        ->filterTable('queryBuilder', queryBuilderFilteringRules())
        ->assertCanSeeTableRecords([$deleted, $other])->assertCanNotSeeTableRecords([$live])->assertCountTableRecords(2);
});

it('uses an explicit same-name custom query builder instead of the automatic constraints', function (): void {
    $match = queryBuilderFilteringProduct(['name' => 'foo', 'quantity' => 1]);
    $other = queryBuilderFilteringProduct(['name' => 'bar', 'quantity' => 1]);
    $unstocked = queryBuilderFilteringProduct(['name' => 'foo', 'quantity' => 0]);
    $page = queryBuilderFilteringPage(QueryBuilderFilteringCustomListProducts::class)
        ->assertTableFilterExists('queryBuilder')->assertTableFilterExists('stocked')->assertTableFilterExists('trashed');
    $filter = $page->instance()->getTable()->getFilter('queryBuilder');
    expect(array_keys($filter->getConstraints()))->toBe(['customName'])
        ->and($filter->getLabel())->toBe('Custom product rules');

    $page->filterTable('stocked')
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('customName', 'contains', ['text' => 'foo'])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other, $unstocked])->assertCountTableRecords(1)
        ->removeTableFilter('queryBuilder')
        ->assertCanSeeTableRecords([$match, $other])->assertCanNotSeeTableRecords([$unstocked])->assertCountTableRecords(2);
});

it('filters a standalone relation manager without escaping its owner or custom filters', function (): void {
    $owner = QueryBuilderFilteringOwner::create(['name' => 'Current owner']);
    $otherOwner = QueryBuilderFilteringOwner::create(['name' => 'Other owner']);
    $match = queryBuilderFilteringProduct(['owner_id' => $owner->id, 'name' => 'foo', 'quantity' => 1]);
    $unstocked = queryBuilderFilteringProduct(['owner_id' => $owner->id, 'name' => 'foo', 'quantity' => 0]);
    $otherName = queryBuilderFilteringProduct(['owner_id' => $owner->id, 'name' => 'bar', 'quantity' => 1]);
    $outside = queryBuilderFilteringProduct(['owner_id' => $otherOwner->id, 'name' => 'foo', 'quantity' => 1]);

    Livewire::test(QueryBuilderFilteringManager::class, ['ownerRecord' => $owner, 'pageClass' => QueryBuilderFilteringListProducts::class])
        ->assertSuccessful()->assertTableFilterExists('queryBuilder')->assertTableFilterExists('stocked')->assertTableFilterExists('trashed')
        ->filterTable('stocked')
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('name', 'contains', ['text' => 'foo'])))
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$unstocked, $otherName, $outside])->assertCountTableRecords(1)
        ->removeTableFilter('queryBuilder')
        ->assertCanSeeTableRecords([$match, $otherName])->assertCanNotSeeTableRecords([$unstocked, $outside])->assertCountTableRecords(2);
});

it('uses inherited dictionary rules in a resource-backed relation manager while retaining its relationship scope', function (): void {
    $owner = QueryBuilderFilteringOwner::create(['name' => 'Current owner']);
    $otherOwner = QueryBuilderFilteringOwner::create(['name' => 'Other owner']);
    $draft = queryBuilderFilteringProduct(['owner_id' => $owner->id, 'status' => 'draft']);
    $published = queryBuilderFilteringProduct(['owner_id' => $owner->id, 'status' => 'published']);
    $archived = queryBuilderFilteringProduct(['owner_id' => $owner->id, 'status' => 'archived']);
    $outside = queryBuilderFilteringProduct(['owner_id' => $otherOwner->id, 'status' => 'published']);

    Livewire::test(QueryBuilderFilteringResourceManager::class, ['ownerRecord' => $owner, 'pageClass' => QueryBuilderFilteringListProducts::class])
        ->assertSuccessful()->assertTableFilterExists('queryBuilder')->assertTableFilterExists('trashed')
        ->filterTable('queryBuilder', queryBuilderFilteringRules(queryBuilderFilteringRule('status', 'is', ['values' => ['draft', 'published']])))
        ->assertCanSeeTableRecords([$draft, $published])->assertCanNotSeeTableRecords([$archived, $outside])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', queryBuilderFilteringRules())
        ->assertCanSeeTableRecords([$draft, $published, $archived])->assertCanNotSeeTableRecords([$outside])->assertCountTableRecords(3);
});
