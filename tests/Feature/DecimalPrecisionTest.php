<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Schemas\Components\StateCasts\NumberStateCast;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\DecimalPrecisionFormHost;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\DecimalPrecisionListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\DecimalPrecisionProduct;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\DecimalPrecisionResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SchemaReader;

require_once __DIR__ . '/../Fixtures/DecimalPrecisionFixtures.php';

beforeEach(function (): void {
    Schema::table('products', function (Blueprint $table): void {
        // SQLite's DECIMAL affinity stores large fractions as lossy REAL values.
        $table->text('exact_value')->nullable();
        $table->text('ten_place_value')->nullable();
        $table->text('cast_float_value')->nullable();
        $table->double('floating_value')->nullable();
        $table->decimal('native_value', 40, 20)->nullable();
        $table->decimal('native_ten_place_value', 30, 10)->nullable();
    });

    $panel = Panel::make()->id('decimal-precision')->path('decimal-precision')->resources([
        DecimalPrecisionResource::class,
    ]);
    Filament::setCurrentPanel($panel);
    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(fn () => DecimalPrecisionResource::registerRoutes($panel));
    Route::getRoutes()->refreshNameLookups();
});

dataset('exact decimal values', [
    'huge positive' => ['12345678901234567890.12345678901234567890', '12,345,678,901,234,567,890.12345678901234567890'],
    'huge negative' => ['-12345678901234567890.12345678901234567890', '-12,345,678,901,234,567,890.12345678901234567890'],
    'above binary integer precision' => ['9007199254740993.00000000000000000001', '9,007,199,254,740,993.00000000000000000001'],
    'forty significant digits' => ['99999999999999999999.99999999999999999999', '99,999,999,999,999,999,999.99999999999999999999'],
    'tiny positive' => ['0.00000000000000000001', '0.00000000000000000001'],
    'tiny negative' => ['-0.00000000000000000001', '-0.00000000000000000001'],
    'zero' => ['0.00000000000000000000', '0.00000000000000000000'],
    'null' => [null, null],
]);

function decimalPrecisionProduct(array $attributes = []): DecimalPrecisionProduct
{
    return DecimalPrecisionProduct::create([
        'name' => 'Precision regression',
        'price' => '1.00',
        ...$attributes,
    ]);
}

function observeDecimalPrecision(Product $record, string $name, ?string $tableLocale = null, ?string $schemaLocale = null): array
{
    return Number::withLocale('en', function () use ($record, $name, $tableLocale, $schemaLocale): array {
        $host = new DecimalPrecisionFormHost();
        $metadata = collect(Schema::getColumns($record->getTable()))->firstWhere('name', $name);
        $column = (new SchemaReader($record))->normalize($metadata);
        $tableColumns = TableGenerator::makeForModel($record);
        $host->setPrecisionTable(Table::make($host)->columns($tableColumns)->defaultNumberLocale($tableLocale));
        $tableColumn = $tableColumns[$name]->record($record);
        $infolist = FilamentSchema::make($host)->model($record)->components(InfolistGenerator::makeForModel($record));
        $entry = collect($infolist->getFlatComponents(withHidden: true))->first(fn ($component) => method_exists($component, 'getName') && $component->getName() === $name);
        // Filament reads locale defaults from the entry's immediate schema, not its outer groups.
        $entry->getContainer()->defaultNumberLocale($schemaLocale);
        $fields = FormGenerator::makeForModel($record, relationManagerView: true);
        $form = FilamentSchema::make($host)->model($record)->statePath('precisionData')->components([$fields[$name]]);
        $form->fill($record->attributesToArray());
        $hydrated = $host->precisionData[$name];
        $dehydrated = $form->getState()[$name];

        return [
            'database_type' => $metadata['type'],
            'model_cast' => $record->getCasts()[$name] ?? null,
            'normalized_type' => $column->getType(),
            'decimal_places' => $column->getDecimalPlaces(),
            'is_numeric' => $column->isNumeric(),
            'is_integer' => $column->isInteger(),
            'raw_value' => $record->getRawOriginal($name),
            'model_value' => $record->getAttribute($name),
            'model_value_type' => get_debug_type($record->getAttribute($name)),
            'table' => $tableColumn->formatState($tableColumn->getState()),
            'infolist' => $entry->formatState($entry->getState()),
            'form_hydrated' => $hydrated,
            'form_hydrated_type' => get_debug_type($hydrated),
            'form_dehydrated' => $dehydrated,
            'form_dehydrated_type' => get_debug_type($dehydrated),
            'form' => $form,
            'field' => $fields[$name],
            'host' => $host,
        ];
    });
}

it('formats every digit of TEXT-backed decimal casts with exact grouping and fixed scale', function (?string $value, ?string $display): void {
    $record = decimalPrecisionProduct(['exact_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'exact_value');

    expect($observed['raw_value'])->toBe($value)
        ->and($observed['model_value'])->toBe($value)
        ->and($observed['normalized_type'])->toBe('decimal')
        ->and($observed['decimal_places'])->toBe(20)
        ->and($observed['table'])->toBe($display)
        ->and($observed['infolist'])->toBe($display);
})->with('exact decimal values');

it('hydrates and dehydrates exact decimal strings without a floating point intermediary', function (?string $value): void {
    $record = decimalPrecisionProduct(['exact_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'exact_value');
    $type = $value === null ? 'null' : 'string';

    expect($observed['form_hydrated'])->toBe($value)
        ->and($observed['form_hydrated_type'])->toBe($type)
        ->and($observed['field']->getState())->toBe($value)
        ->and($observed['form_dehydrated'])->toBe($value)
        ->and($observed['form_dehydrated_type'])->toBe($type);

    $record->forceFill(['exact_value' => $observed['form_dehydrated']])->save();

    expect($record->refresh()->getRawOriginal('exact_value'))->toBe($value)
        ->and($record->exact_value)->toBe($value);
})->with('exact decimal values');

it('retains ten-place fractional digits and trailing zeroes through generated forms', function (string $value): void {
    $record = decimalPrecisionProduct(['ten_place_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'ten_place_value');

    expect($observed['decimal_places'])->toBe(10)
        ->and($observed['table'])->toBe($value)
        ->and($observed['infolist'])->toBe($value)
        ->and($observed['form_hydrated'])->toBe($value)
        ->and($observed['form_dehydrated'])->toBe($value);

    $record->forceFill(['ten_place_value' => $observed['form_dehydrated']])->save();

    expect($record->refresh()->getRawOriginal('ten_place_value'))->toBe($value)
        ->and($record->ten_place_value)->toBe($value);
})->with(['0.1234567890', '0.0000004000', '0.0000000001', '-0.0000000001', '1.2345678910']);

it('preserves a small native ten-place decimal in displays and unchanged Livewire edits without a model cast', function (): void {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Native DECIMAL scale and exact storage require a decimal-capable database.');
    }

    $value = '0.0000004000';
    $record = decimalPrecisionProduct(['native_ten_place_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'native_ten_place_value');

    expect($observed['model_cast'])->toBeNull()
        ->and($observed['decimal_places'])->toBe(10)
        ->and($observed['raw_value'])->toBe($value)
        ->and($observed['table'])->toBe($value)
        ->and($observed['infolist'])->toBe($value)
        ->and($observed['form_hydrated'])->toBe($value)
        ->and($observed['form_dehydrated'])->toBe($value);

    $page = Livewire::test(DecimalPrecisionListProducts::class)
        ->mountAction(TestAction::make('edit')->table($record))
        ->assertActionMounted(TestAction::make('edit')->table($record));

    expect($page->instance()->mountedActions[0]['data']['native_ten_place_value'])->toBe($value);

    $page->callMountedAction()->assertHasNoFormErrors()->assertActionNotMounted();

    expect($record->refresh()->getRawOriginal('native_ten_place_value'))->toBe($value);
});

it('uses string-safe numeric text inputs without PHP or JavaScript number conversion', function (string $name): void {
    $record = decimalPrecisionProduct()->refresh();
    $observed = observeDecimalPrecision($record, $name);
    $field = $observed['field'];

    expect($field)->toBeInstanceOf(TextInput::class);

    $html = $field->toHtml();
    $document = new DOMDocument();
    expect($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();
    $input = (new DOMXPath($document))->query('//input[@id="' . $field->getId() . '"]')->item(0);

    if (! $input instanceof DOMElement) {
        throw new RuntimeException('The generated numeric input was not rendered.');
    }

    expect($field->getType())->toBe('text')
        ->and($field->getInputMode())->toBeIn(['numeric', 'decimal'])
        ->and(collect($field->getStateCasts())->contains(fn ($cast): bool => $cast instanceof NumberStateCast))->toBeFalse()
        ->and($input->getAttribute('type'))->toBe('text')
        ->and($input->getAttribute('inputmode'))->toBeIn(['numeric', 'decimal'])
        ->and($html)->not->toMatch('/wire:model[^=\s]*\.number|parseFloat\s*\(|parseInt\s*\(|\bNumber\s*\(/');
})->with(['exact_value', 'ten_place_value', 'floating_value', 'cast_float_value', 'quantity']);

it('does not round native floating point fractions or tiny values to zero', function (?string $value): void {
    $record = decimalPrecisionProduct(['floating_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'floating_value');

    expect($observed['normalized_type'])->toBe('float')
        ->and($observed['decimal_places'])->toBeNull()
        ->and($observed['is_numeric'])->toBeTrue()
        ->and($observed['is_integer'])->toBeFalse();

    if ($value === null) {
        expect($observed['table'])->toBeNull()
            ->and($observed['infolist'])->toBeNull()
            ->and($observed['form_hydrated'])->toBeNull()
            ->and($observed['form_dehydrated'])->toBeNull();

        return;
    }

    // Floats are already approximate; compare their value, not arbitrary exact DB digits.
    foreach (['table', 'infolist'] as $surface) {
        $display = str_replace(',', '', $observed[$surface]);

        expect(is_numeric($display))->toBeTrue()
            ->and((float) $display)->toBe((float) $record->floating_value);
    }

    expect($observed['form_hydrated_type'])->toBe('string')
        ->and($observed['form_dehydrated_type'])->toBe('string')
        ->and((float) $observed['form_dehydrated'])->toBe((float) $record->floating_value);
})->with(['0.0000004000', '0.0000000001', '-0.0000000001', '1.234567891', '1234.56789', '0', null]);

it('treats Eloquent floating point casts as fractional rather than integer displays', function (string $cast): void {
    $record = decimalPrecisionProduct(['cast_float_value' => '0.0000000001']);
    $record->mergeCasts(['cast_float_value' => $cast])->refresh();
    $observed = observeDecimalPrecision($record, 'cast_float_value');

    expect($observed['model_value_type'])->toBe('float')
        ->and($observed['normalized_type'])->toBe('float')
        ->and($observed['decimal_places'])->toBeNull()
        ->and($observed['is_integer'])->toBeFalse()
        ->and((float) str_replace(',', '', $observed['table']))->toBe(0.0000000001)
        ->and((float) str_replace(',', '', $observed['infolist']))->toBe(0.0000000001)
        ->and($observed['form_hydrated_type'])->toBe('string')
        ->and($observed['form_dehydrated_type'])->toBe('string');
})->with(['float', 'double', 'real']);

it('distinguishes floating and unconstrained numeric metadata from fixed-scale decimals and integers', function (string $nativeType, string $definition, ?string $cast, string $type, ?int $scale, bool $integer): void {
    $column = new Column([
        'name' => 'value',
        'type_name' => $nativeType,
        'type' => $definition,
        'nullable' => true,
    ], $cast, 'mysql');

    expect($column->getType())->toBe($type)
        ->and($column->getDecimalPlaces())->toBe($scale)
        ->and($column->isNumeric())->toBeTrue()
        ->and($column->isInteger())->toBe($integer);
})->with([
    'native float' => ['float', 'float', null, 'float', null, false],
    'native double' => ['double', 'double', null, 'float', null, false],
    'native real' => ['real', 'real', null, 'float', null, false],
    'Postgres float4' => ['float4', 'float4', null, 'float', null, false],
    'Postgres float8' => ['float8', 'float8', null, 'float', null, false],
    'float cast' => ['text', 'text', 'float', 'float', null, false],
    'double cast overrides fixed scale' => ['decimal', 'decimal(40,20)', 'double', 'float', null, false],
    'real cast' => ['text', 'text', 'real', 'float', null, false],
    'native scale' => ['decimal', 'decimal(40,20)', null, 'decimal', 20, false],
    'zero scale' => ['numeric', 'numeric(40,0)', null, 'decimal', 0, false],
    'unconstrained numeric' => ['numeric', 'numeric', null, 'decimal', null, false],
    'decimal cast overrides native scale' => ['decimal', 'decimal(40,20)', 'decimal:10', 'decimal', 10, false],
    'TEXT decimal cast' => ['text', 'text', 'decimal:20', 'decimal', 20, false],
    'native integer' => ['bigint', 'bigint', null, 'integer', 0, true],
    'integer cast overrides native scale' => ['decimal', 'decimal(40,20)', 'integer', 'integer', 0, true],
]);

it('persists exact decimal input through a real Livewire resource create action', function (?string $value): void {
    Livewire::test(DecimalPrecisionListProducts::class)
        ->assertSuccessful()
        ->callAction('create', data: [
            'name' => 'Created exact decimal',
            'price' => '1.00',
            'quantity' => '0',
            'status' => 'draft',
            'exact_value' => $value,
        ])
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('create');

    $record = DecimalPrecisionProduct::where('name', 'Created exact decimal')->sole();

    expect($record->getRawOriginal('exact_value'))->toBe($value)
        ->and($record->exact_value)->toBe($value);
})->with([
    'huge' => ['12345678901234567890.12345678901234567890'],
    'negative tiny' => ['-0.00000000000000000001'],
    'zero' => ['0.00000000000000000000'],
    'null' => [null],
]);

it('preserves an unchanged huge decimal when mounting and submitting a real Livewire edit action', function (): void {
    $value = '12345678901234567890.12345678901234567890';
    $record = decimalPrecisionProduct(['exact_value' => $value]);
    $page = Livewire::test(DecimalPrecisionListProducts::class)
        ->assertSuccessful()
        ->mountAction(TestAction::make('edit')->table($record))
        ->assertActionMounted(TestAction::make('edit')->table($record));
    $hydrated = $page->instance()->mountedActions[0]['data']['exact_value'];

    // Submit before asserting hydration so persistence regressions are exercised too.
    $page->callMountedAction()->assertHasNoFormErrors()->assertActionNotMounted();

    expect($record->refresh()->getRawOriginal('exact_value'))->toBe($value)
        ->and($record->exact_value)->toBe($value)
        ->and($hydrated)->toBe($value);
});

it('persists changed decimal input through a real Livewire resource edit action', function (?string $value): void {
    $record = decimalPrecisionProduct(['exact_value' => '1.00000000000000000000']);

    Livewire::test(DecimalPrecisionListProducts::class)
        ->assertSuccessful()
        ->callAction(TestAction::make('edit')->table($record), data: ['exact_value' => $value])
        ->assertHasNoFormErrors()
        ->assertActionNotMounted();

    expect($record->refresh()->getRawOriginal('exact_value'))->toBe($value)
        ->and($record->exact_value)->toBe($value);
})->with([
    'huge negative' => ['-12345678901234567890.12345678901234567890'],
    'tiny' => ['0.00000000000000000001'],
    'zero' => ['0.00000000000000000000'],
    'null' => [null],
]);

it('normalizes blank nullable numeric strings to null through state casting dehydration and persistence', function (string $blank): void {
    $record = decimalPrecisionProduct(['exact_value' => '1.00000000000000000000']);
    $observed = observeDecimalPrecision($record, 'exact_value');
    $observed['form']->fill(['exact_value' => $blank]);

    expect($observed['field']->getState())->toBeNull()
        ->and($observed['form']->getState()['exact_value'])->toBeNull();

    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction(TestAction::make('edit')->table($record), data: ['exact_value' => $blank])
        ->assertHasNoFormErrors()
        ->assertActionNotMounted();

    expect($record->refresh()->getRawOriginal('exact_value'))->toBeNull()
        ->and($record->exact_value)->toBeNull();
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'tabs' => ["\t\t"],
    'mixed whitespace' => [" \t\n "],
]);

it('still validates numeric input when the generated input is text', function (): void {
    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction('create', data: [
            'name' => 'Invalid decimal',
            'price' => '1.00',
            'quantity' => '0',
            'status' => 'draft',
            'exact_value' => 'not-a-number',
        ])
        ->assertHasFormErrors(['exact_value' => 'numeric']);

    expect(DecimalPrecisionProduct::where('name', 'Invalid decimal')->exists())->toBeFalse();
});

it('rejects whitespace in required numeric fields instead of accepting it as zero', function (string $blank): void {
    $record = decimalPrecisionProduct();

    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction(TestAction::make('edit')->table($record), data: ['price' => $blank])
        ->assertHasFormErrors(['price' => 'required'])
        ->assertActionMounted(TestAction::make('edit')->table($record));

    expect($record->refresh()->price)->toBe('1.00');
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'tabs' => ["\t\t"],
    'mixed whitespace' => [" \t\n "],
]);

it('rejects nonnumeric types in required numeric fields without persisting coerced values', function (mixed $value, string $rule): void {
    $record = decimalPrecisionProduct();

    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction(TestAction::make('edit')->table($record), data: ['price' => $value])
        ->assertHasFormErrors(['price' => $rule])
        ->assertActionMounted(TestAction::make('edit')->table($record));

    expect($record->refresh()->price)->toBe('1.00');
})->with([
    'true' => [true, 'numeric'],
    'false' => [false, 'numeric'],
    'array with a numeric string' => [['1.00'], 'numeric'],
    'empty array' => [[], 'required'],
    'null' => [null, 'required'],
]);

it('accepts native numeric grammar as strings and persists decimal casts at their full scale', function (string $value, string $decimal): void {
    $record = decimalPrecisionProduct(['exact_value' => '2.00000000000000000000']);
    $observed = observeDecimalPrecision($record, 'exact_value');
    $observed['form']->fill(['exact_value' => $value]);

    expect($observed['host']->precisionData['exact_value'])->toBe($value)
        ->and($observed['field']->getState())->toBe($value)
        ->and($observed['form']->getState()['exact_value'])->toBe($value);

    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction(TestAction::make('edit')->table($record), data: ['exact_value' => $value])
        ->assertHasNoFormErrors()
        ->assertActionNotMounted();

    expect($record->refresh()->getRawOriginal('exact_value'))->toBe($value)
        ->and($record->exact_value)->toBe($decimal);
})->with([
    'explicit positive sign' => ['+1', '1.00000000000000000000'],
    'leading decimal point' => ['.1', '0.10000000000000000000'],
    'trailing decimal point' => ['1.', '1.00000000000000000000'],
    'trailing decimal point and exponent' => ['1.e3', '1000.00000000000000000000'],
]);

it('rejects hostile scientific exponents during create and edit validation before any database write', function (string $value): void {
    $original = '12345678901234567890.12345678901234567890';
    $record = decimalPrecisionProduct(['exact_value' => $original]);
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(?:insert|update|delete)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction('create', data: [
            'name' => 'Rejected exponent',
            'price' => '1.00',
            'quantity' => '0',
            'status' => 'draft',
            'exact_value' => $value,
        ])
        ->assertHasFormErrors(['exact_value'])
        ->assertActionMounted('create');

    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction(TestAction::make('edit')->table($record), data: ['exact_value' => $value])
        ->assertHasFormErrors(['exact_value'])
        ->assertActionMounted(TestAction::make('edit')->table($record));

    expect($writes)->toBe([])
        ->and(DecimalPrecisionProduct::count())->toBe(1)
        ->and($record->refresh()->getRawOriginal('exact_value'))->toBe($original)
        ->and($record->exact_value)->toBe($original);
})->with([
    'huge positive exponent' => ['1e20000000'],
    'huge negative exponent' => ['1e-20000000'],
    'zero with an overflowing exponent' => ['0e999999999999999999999'],
]);

it('honors independent generated table and schema number locales before the app locale', function (string $appLocale, ?string $tableLocale, ?string $schemaLocale, string $tableDisplay, string $infolistDisplay): void {
    config(['app.locale' => $appLocale]);
    $value = '12345678901234567890.12345678901234567890';
    $record = decimalPrecisionProduct(['exact_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'exact_value', $tableLocale, $schemaLocale);

    expect($observed['table'])->toBe($tableDisplay)
        ->and($observed['infolist'])->toBe($infolistDisplay)
        ->and($observed['form_hydrated'])->toBe($value)
        ->and($observed['form_dehydrated'])->toBe($value);
})->with([
    'app fallback' => ['de', null, null, '12.345.678.901.234.567.890,12345678901234567890', '12.345.678.901.234.567.890,12345678901234567890'],
    'table override only' => ['en', 'de', null, '12.345.678.901.234.567.890,12345678901234567890', '12,345,678,901,234,567,890.12345678901234567890'],
    'schema override only' => ['en', null, 'de', '12,345,678,901,234,567,890.12345678901234567890', '12.345.678.901.234.567.890,12345678901234567890'],
]);

it('reads native DECIMAL scale and round-trips all forty digits without an Eloquent cast', function (string $value, string $display): void {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite DECIMAL uses REAL affinity and cannot store an exact DECIMAL(40,20).');
    }

    $record = decimalPrecisionProduct(['native_value' => $value])->refresh();
    $observed = observeDecimalPrecision($record, 'native_value');

    expect($observed['model_cast'])->toBeNull()
        ->and($observed['database_type'])->toMatch('/(?:decimal|numeric)\(40,\s*20\)/i')
        ->and($observed['raw_value'])->toBe($value)
        ->and($observed['model_value'])->toBe($value)
        ->and($observed['decimal_places'])->toBe(20)
        ->and($observed['table'])->toBe($display)
        ->and($observed['infolist'])->toBe($display)
        ->and($observed['form_hydrated'])->toBe($value)
        ->and($observed['form_dehydrated'])->toBe($value);

    $record->forceFill(['native_value' => $observed['form_dehydrated']])->save();

    expect($record->refresh()->getRawOriginal('native_value'))->toBe($value);
})->with([
    'huge' => ['12345678901234567890.12345678901234567890', '12,345,678,901,234,567,890.12345678901234567890'],
    'negative tiny' => ['-0.00000000000000000001', '-0.00000000000000000001'],
]);

it('persists native DECIMAL create and unchanged edit submissions without a model cast', function (): void {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Exact native DECIMAL persistence requires a database with decimal storage, not SQLite REAL.');
    }

    $value = '12345678901234567890.12345678901234567890';
    Livewire::test(DecimalPrecisionListProducts::class)
        ->callAction('create', data: [
            'name' => 'Native exact decimal',
            'price' => '1.00',
            'quantity' => '0',
            'status' => 'draft',
            'native_value' => $value,
        ])
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('create');
    $record = DecimalPrecisionProduct::where('name', 'Native exact decimal')->sole();

    expect($record->getCasts())->not->toHaveKey('native_value')
        ->and($record->getRawOriginal('native_value'))->toBe($value);

    Livewire::test(DecimalPrecisionListProducts::class)
        ->mountAction(TestAction::make('edit')->table($record))
        ->assertActionMounted(TestAction::make('edit')->table($record))
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertActionNotMounted();

    expect($record->refresh()->getRawOriginal('native_value'))->toBe($value);
});
