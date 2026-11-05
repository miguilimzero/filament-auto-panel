<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;

class ProductResource extends AutoResource
{
    protected static ?string $model = Product::class;
}
