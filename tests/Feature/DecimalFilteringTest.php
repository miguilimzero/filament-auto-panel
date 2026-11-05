<?php

namespace Miguilim\FilamentAutoPanel\Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\Operators\IsFilledOperator;
use Filament\Schemas\Components\StateCasts\NumberStateCast;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Filament\Filters\NumericComparisonOperator;
use Miguilim\FilamentAutoPanel\Filament\Forms\NumericInput;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceListRecords;
use Miguilim\FilamentAutoPanel\Filament\StateCasts\NumericStringStateCast;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use RuntimeException;

class DecimalFilteringProduct extends Product
{
    protected $table = 'products';

    protected function casts(): array
    {
        return [
            'exact_value' => 'decimal:20',
            'cast_float_value' => 'float',
        ];
    }
}

class DecimalFilteringResource extends AutoResource
{
    protected static ?string $model = DecimalFilteringProduct::class;

    public static function getPages(): array
    {
        return ['index' => DecimalFilteringListProducts::route('/')];
    }
}

class DecimalFilteringListProducts extends AutoResourceListRecords
{
    protected static string $resource = DecimalFilteringResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)->deferFilters(false);
    }
}

beforeEach(function (): void {
    $this->app['translator']->addNamespace('filament-query-builder', __DIR__ . '/../../vendor/filament/query-builder/resources/lang');

    Schema::table('products', function (Blueprint $table): void {
        $table->decimal('native_value', 40, 20)->nullable();
        $table->decimal('exact_value', 40, 20)->nullable();
        $table->double('floating_value')->nullable();
        $table->text('cast_float_value')->nullable();
    });

    $panel = Panel::make()->id('decimal-filtering')->path('decimal-filtering')->resources([
        DecimalFilteringResource::class,
    ]);
    Filament::setCurrentPanel($panel);
    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(fn () => DecimalFilteringResource::registerRoutes($panel));
    Route::getRoutes()->refreshNameLookups();
});

dataset('decimal filtering columns', ['native_value', 'exact_value', 'floating_value', 'cast_float_value']);

dataset('decimal filtering comparisons', [
    'equality' => ['equals', '=', [1]],
    'inverse equality' => ['equals.inverse', '!=', [0, 2]],
    'minimum' => ['isMin', '>=', [1, 2]],
    'inverse minimum' => ['isMin.inverse', '<', [0]],
    'maximum' => ['isMax', '<=', [0, 1]],
    'inverse maximum' => ['isMax.inverse', '>', [2]],
]);

function decimalFilteringProduct(array $attributes = []): DecimalFilteringProduct
{
    return DecimalFilteringProduct::create(['name' => 'Decimal filtering', 'price' => '1.00', ...$attributes]);
}

function decimalFilteringRules(string $column, string $operator, mixed $number = null): array
{
    return ['rules' => ['r1' => [
        'type' => $column,
        'data' => ['operator' => $operator, 'settings' => ['number' => $number]],
    ]]];
}

function decimalFilteringPage(): Testable
{
    return Livewire::test(DecimalFilteringListProducts::class)
        ->assertSuccessful()
        ->assertTableFilterExists('queryBuilder', fn ($filter): bool => $filter instanceof QueryBuilder);
}

function decimalFilteringNumberField(Testable $page): NumericInput
{
    $fields = collect($page->instance()->getTableFiltersForm()->getFlatFields(withHidden: true));
    $numbers = $fields->filter(fn ($field): bool => $field->getName() === 'number');
    expect($numbers)->toHaveCount(1)
        ->and($fields->contains(fn ($field): bool => $field->getName() === 'aggregate'))->toBeFalse();

    return $numbers->first();
}

it('uses native number constraints with exact operators and matching integer and nullable flags', function (): void {
    $filter = decimalFilteringPage()->instance()->getTable()->getFilter('queryBuilder');

    foreach (['id', 'quantity', 'price', 'native_value', 'exact_value', 'floating_value', 'cast_float_value'] as $column) {
        $integer = in_array($column, ['id', 'quantity'], true);
        $nullable = ! in_array($column, ['id', 'quantity', 'price'], true);
        $constraint = $filter->getConstraint($column);

        expect($constraint)->toBeInstanceOf(NumberConstraint::class)
            ->and($constraint->isInteger())->toBe($integer)
            ->and($constraint->isNullable())->toBe($nullable)
            ->and(array_keys($constraint->getOperators()))->toBe($nullable
                ? ['isMin', 'isMax', 'equals', 'isFilled']
                : ['isMin', 'isMax', 'equals']);

        foreach (['equals', 'isMin', 'isMax'] as $name) {
            expect($constraint->getOperator($name))->toBeInstanceOf(NumericComparisonOperator::class)
                ->and($constraint->getOperator($name)->isInteger())->toBe($integer);
        }

        if ($nullable) {
            expect($constraint->getOperator('isFilled'))->toBeInstanceOf(IsFilledOperator::class);
        }
    }
});

it('filters representable fractions with every comparison and inverse while excluding nulls', function (string $operator, string $comparison, array $visible, string $column): void {
    $records = [];
    foreach (['12.25', '12.50', '12.75', null] as $value) {
        $records[] = decimalFilteringProduct([$column => $value]);
    }

    $page = decimalFilteringPage()
        ->filterTable('queryBuilder', decimalFilteringRules($column, $operator, '12.50'))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', '12.50')
        ->assertCanSeeTableRecords(array_map(fn (int $index) => $records[$index], $visible))
        ->assertCanNotSeeTableRecords(array_values(array_diff_key($records, array_flip($visible))))
        ->assertCountTableRecords(count($visible));

    expect($page->instance()->getFilteredTableQuery()->toBase()->getBindings())->toBe(['12.50']);
})->with('decimal filtering comparisons')->with('decimal filtering columns');

it('keeps exact decimal strings in mounted settings query bindings and native summaries', function (string $operator, string $comparison, array $visible, string $column): void {
    $number = '12345678901234567890.12345678901234567890';
    $page = decimalFilteringPage()
        ->filterTable('queryBuilder', decimalFilteringRules($column, $operator, $number))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', $number);
    $query = $page->instance()->getFilteredTableQuery()->toBase();
    $filter = $page->instance()->getTable()->getFilter('queryBuilder');
    $indicators = $filter->getIndicators();
    [$name] = explode('.', $operator);
    $key = match ($name) {
        'isMin' => 'is_min',
        'isMax' => 'is_max',
        'equals' => 'equals',
    };
    $direction = str_ends_with($operator, '.inverse') ? 'inverse' : 'direct';

    expect($query->getBindings())->toBe([$number])
        ->and($query->toSql())->toContain($comparison . ' ?')
        ->and($indicators)->toHaveCount(1)
        ->and((string) $indicators['r1']->getLabel())->toContain($number)
        ->and((string) $indicators['r1']->getLabel())->toBe(__("filament-query-builder::query-builder.operators.number.{$key}.summary.{$direction}", [
            'attribute' => $filter->getConstraint($column)->getAttributeLabel(),
            'number' => $number,
        ]));
})->with('decimal filtering comparisons')->with('decimal filtering columns');

it('uses one required numeric text input with string casts and no JavaScript number coercion', function (string $column): void {
    $page = decimalFilteringPage();
    $number = '-9007199254740993.00000000000000000001';

    foreach (['equals', 'equals.inverse', 'isMin', 'isMin.inverse', 'isMax', 'isMax.inverse'] as $operator) {
        $page->filterTable('queryBuilder', decimalFilteringRules($column, $operator, $number))
            ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', $number);
        $field = decimalFilteringNumberField($page);

        expect($field)->toBeInstanceOf(NumericInput::class)
            ->and($field->isRequired())->toBeTrue()
            ->and($field->isNumeric())->toBeTrue()
            ->and($field->isInteger())->toBeFalse()
            ->and($field->getType())->toBe('text')
            ->and($field->getInputMode())->toBe('decimal')
            ->and($field->getState())->toBe($number)
            ->and(collect($field->getStateCasts())->contains(fn ($cast): bool => $cast instanceof NumericStringStateCast))->toBeTrue()
            ->and(collect($field->getStateCasts())->contains(fn ($cast): bool => $cast instanceof NumberStateCast))->toBeFalse();

        $state = $page->instance()->getTableFiltersForm()->getState();
        expect(array_values($state['queryBuilder']['rules'])[0]['data']['settings']['number'])->toBe($number);

        $html = $field->toHtml();
        $document = new DOMDocument();
        expect($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();
        $input = (new DOMXPath($document))->query('//input[@id="' . $field->getId() . '"]')->item(0);

        if (! $input instanceof DOMElement) {
            throw new RuntimeException('The decimal comparison input was not rendered.');
        }

        expect($input->getAttribute('type'))->toBe('text')
            ->and($input->getAttribute('inputmode'))->toBe('decimal')
            ->and($html)->not->toMatch('/wire:model[^=\s]*\.number|parseFloat\s*\(|parseInt\s*\(|\bNumber\s*\(/');
    }
})->with('decimal filtering columns');

it('retains fractional syntax and trailing zeroes rather than formatting summaries or bindings', function (string $number): void {
    $page = decimalFilteringPage()
        ->filterTable('queryBuilder', decimalFilteringRules('native_value', 'equals', $number))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', $number);

    expect($page->instance()->getFilteredTableQuery()->toBase()->getBindings())->toBe([$number])
        ->and((string) $page->instance()->getTable()->getFilter('queryBuilder')->getIndicators()['r1']->getLabel())->toContain($number);
})->with([
    '-0.00000000000000000001', '0.00000000000000000000', '-00012.3400',
    '+12.3400', '.125', '12.', '1.234567890123456789e-20',
]);

it('validates scientific expansion boundaries without imposing floating point limits', function (string $number, bool $valid): void {
    $page = decimalFilteringPage()->filterTable('queryBuilder', decimalFilteringRules('native_value', 'equals', '1.25'));
    $field = decimalFilteringNumberField($page);

    expect(Validator::make(['number' => $number], ['number' => $field->getValidationRules()])->passes())->toBe($valid);
})->with([
    ['1e+10000', true], ['1e-10000', true], ['1e+00010000', true],
    ['1e-00000000000000000000', true], ['1.25e+0', true],
    ['1e+10001', false], ['1e-10001', false], ['1e00010001', false],
    ['1e-00010001', false], ['1e99999999999999999999999999', false],
]);

it('rejects invalid mounted decimal rules without dropping valid siblings', function (mixed $number): void {
    $match = decimalFilteringProduct(['name' => 'Match', 'native_value' => '1.25']);
    $other = decimalFilteringProduct(['name' => 'Other', 'native_value' => '2.50']);
    $rules = decimalFilteringRules('native_value', 'equals', $number);
    $rules['rules']['r2'] = ['type' => 'name', 'data' => ['operator' => 'contains', 'settings' => ['text' => 'Match']]];
    $page = decimalFilteringPage()->filterTable('queryBuilder', $rules)
        ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other])->assertCountTableRecords(1);

    expect($page->instance()->getFilteredTableQuery()->toBase()->getBindings())->not->toContain($number)
        ->and(fn () => $page->instance()->getTableFiltersForm()->getState())->toThrow(ValidationException::class);
})->with([[null], [''], [' '], ['not-a-number'], ['12,34'], ['1e+10001'], ['1e-10001'], ['1e99999999999999999999999999']]);

it('supports native filled and inverse filled rules for nullable fractional columns', function (string $column): void {
    $null = decimalFilteringProduct([$column => null]);
    $zero = decimalFilteringProduct([$column => '0.00']);
    $fraction = decimalFilteringProduct([$column => '0.25']);

    decimalFilteringPage()
        ->filterTable('queryBuilder', decimalFilteringRules($column, 'isFilled'))
        ->assertCanSeeTableRecords([$zero, $fraction])->assertCanNotSeeTableRecords([$null])->assertCountTableRecords(2)
        ->filterTable('queryBuilder', decimalFilteringRules($column, 'isFilled.inverse'))
        ->assertCanSeeTableRecords([$null])->assertCanNotSeeTableRecords([$zero, $fraction])->assertCountTableRecords(1);
})->with('decimal filtering columns');

it('combines inclusive exact decimal bounds in separate native rules', function (): void {
    $below = decimalFilteringProduct(['native_value' => '-0.50']);
    $lower = decimalFilteringProduct(['native_value' => '-0.25']);
    $upper = decimalFilteringProduct(['native_value' => '0.00']);
    $above = decimalFilteringProduct(['native_value' => '0.25']);
    $rules = decimalFilteringRules('native_value', 'isMin', '-0.2500');
    $rules['rules']['r2'] = decimalFilteringRules('native_value', 'isMax', '0.0000')['rules']['r1'];
    $page = decimalFilteringPage()->filterTable('queryBuilder', $rules)
        ->assertCanSeeTableRecords([$lower, $upper])->assertCanNotSeeTableRecords([$below, $above])->assertCountTableRecords(2);

    expect($page->instance()->getFilteredTableQuery()->toBase()->getBindings())->toBe(['-0.2500', '0.0000']);
});

it('distinguishes adjacent native DECIMAL values at the twentieth fractional digit', function (string $operator, string $comparison, array $visible, string $column, array $values): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite DECIMAL affinity cannot store these exact forty-digit values; exact bindings are covered separately.');
    }

    $records = [];
    foreach ($values as $value) {
        $record = decimalFilteringProduct([$column => $value])->refresh();
        expect($record->getRawOriginal($column))->toBe($value);
        $records[] = $record;
    }

    $page = decimalFilteringPage()
        ->filterTable('queryBuilder', decimalFilteringRules($column, $operator, $values[1]))
        ->assertSet('tableFilters.queryBuilder.rules.r1.data.settings.number', $values[1])
        ->assertCanSeeTableRecords(array_map(fn (int $index) => $records[$index], $visible))
        ->assertCanNotSeeTableRecords(array_values(array_diff_key($records, array_flip($visible))))
        ->assertCountTableRecords(count($visible));

    expect($page->instance()->getFilteredTableQuery()->toBase()->getBindings())->toBe([$values[1]]);
})->with('decimal filtering comparisons')->with(['native_value', 'exact_value'])->with([
    'positive' => [[
        '12345678901234567890.12345678901234567890',
        '12345678901234567890.12345678901234567891',
        '12345678901234567890.12345678901234567892',
    ]],
    'negative' => [[
        '-12345678901234567890.12345678901234567892',
        '-12345678901234567890.12345678901234567891',
        '-12345678901234567890.12345678901234567890',
    ]],
]);
