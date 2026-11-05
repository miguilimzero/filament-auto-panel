<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures\Models;

class SecondaryProduct extends Product
{
    protected $connection = 'secondary';

    protected $table = 'products';
}
