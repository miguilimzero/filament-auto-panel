<?php

use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SchemaReader;

class DecimalPrecisionFormHost extends ListProducts
{
    public array $precisionData = [];

    public function setPrecisionTable(Table $table): void
    {
        $this->table = $table;
    }
}

beforeEach(function (): void {
    Schema::table('products', function (Blueprint $table): void {
        $table->decimal('precise_value', 30, 10)->nullable();

    });
});

function observeDecimalPrecision(Product $record, string $name): array
{
    return Number::withLocale('en', function () use ($record, $name): array {
        $host = new DecimalPrecisionFormHost();
        $metadata = collect(Schema::getColumns('products'))->firstWhere('name', $name);
        $column = (new SchemaReader($record))->normalize($metadata);
        $tableColumns = TableGenerator::makeForModel($record);
        $host->setPrecisionTable(Table::make($host)->columns($tableColumns));
        $tableColumn = $tableColumns[$name]->record($record);
        $infolist = FilamentSchema::make($host)->model($record)->components(InfolistGenerator::makeForModel($record));
        $entry = collect($infolist->getFlatComponents(withHidden: true))->first(fn ($component) => method_exists($component, 'getName') && $component->getName() === $name);
        $fields = FormGenerator::makeForModel($record, relationManagerView: true);
        $form = FilamentSchema::make($host)->model($record)->statePath('precisionData')->components([$fields[$name]]);
        $form->fill($record->attributesToArray());

        return [
            'database_type' => $metadata['type'],
            'model_cast' => $record->getCasts()[$name] ?? null,
            'normalized_type' => $column->getType(),
            'decimal_places' => $column->getDecimalPlaces(),
            'raw_value' => $record->getRawOriginal($name),
            'model_value' => $record->getAttribute($name),
            'model_value_type' => get_debug_type($record->getAttribute($name)),
            'table' => $tableColumn->formatState($tableColumn->getState()),
            'infolist' => $entry->formatState($entry->getState()),
            'form_hydrated' => $host->precisionData[$name],
            'form_dehydrated' => $form->getState()[$name],
        ];
    });
}

it('retains nonzero ten-place decimal values across generated displays and form persistence', function (string $value): void {
    $record = (new Product())->mergeCasts(['precise_value' => 'decimal:10']);
    $record->forceFill(['name' => 'Decimal investigation', 'price' => '1.00', 'precise_value' => $value])->save();
    $record->refresh();
    $observed = observeDecimalPrecision($record, 'precise_value');

    expect($observed['decimal_places'])->toBe(10)
        ->and($observed['table'])->toBe($value)
        ->and($observed['infolist'])->toBe($value)
        ->and(is_numeric($observed['form_hydrated']))->toBeTrue()
        ->and((float) $observed['form_hydrated'])->not->toBe(0.0);

    $record->forceFill(['precise_value' => $observed['form_dehydrated']])->save();

    expect($record->refresh()->precise_value)->toBe($value);
})->with(['0.1234567890', '0.0000000001', '-0.0000000001', '1.2345678910']);

it('reads ten decimal places from native DECIMAL metadata when the database exposes scale', function (): void {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('Laravel SQLite migrations omit decimal precision and scale.');
    }

    $record = Product::create(['name' => 'Native decimal', 'price' => '1.00', 'precise_value' => '0.0000000001']);
    $observed = observeDecimalPrecision($record->refresh(), 'precise_value');

    expect($observed['decimal_places'])->toBe(10)
        ->and($observed['table'])->toBe('0.0000000001')
        ->and($observed['infolist'])->toBe('0.0000000001')
        ->and((float) $observed['form_hydrated'])->not->toBe(0.0);
});
