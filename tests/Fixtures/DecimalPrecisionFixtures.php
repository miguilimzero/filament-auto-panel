<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Tables\Table;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;

class DecimalPrecisionProduct extends Product
{
    protected $table = 'products';

    protected function casts(): array
    {
        return [
            'exact_value' => 'decimal:20',
            'ten_place_value' => 'decimal:10',
            'cast_float_value' => 'float',
        ];
    }
}

class DecimalPrecisionResource extends AutoResource
{
    protected static ?string $model = DecimalPrecisionProduct::class;

    public static function getPages(): array
    {
        return ['index' => DecimalPrecisionListProducts::route('/')];
    }

    public static function getActions(): array
    {
        return [AutoEditAction::make()->showOnTable()];
    }
}

class DecimalPrecisionListProducts extends AutoResourceListRecords
{
    protected static string $resource = DecimalPrecisionResource::class;
}

class DecimalPrecisionFormHost extends ListProducts
{
    public array $precisionData = [];

    public function setPrecisionTable(Table $table): void
    {
        $this->table = $table;
    }
}
