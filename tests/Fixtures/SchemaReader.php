<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

class SchemaReader extends FormGenerator
{
    public function normalize(array $column): Column
    {
        return $this->constructColumnInstance($column);
    }
}
