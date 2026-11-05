<?php

use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SchemaReader;

it('normalizes native schema metadata without Doctrine', function (string $typeName, string $type, string $expectedType, int $length, int $scale, bool $numeric): void {
    $column = (new SchemaReader(Product::class))->normalize([
        'name' => 'value',
        'type_name' => $typeName,
        'type' => $type,
        'nullable' => false,
    ]);

    expect($column->getName())->toBe('value')
        ->and($column->getType())->toBe($expectedType)
        ->and($column->getNotNull())->toBeTrue()
        ->and($column->getLength())->toBe($length)
        ->and($column->getDecimalPlaces())->toBe($scale)
        ->and($column->isNumeric())->toBe($numeric);
})->with([
    'mysql boolean' => ['tinyint', 'tinyint(1)', 'boolean', 0, 0, false],
    'ordinary tinyint' => ['tinyint', 'tinyint', 'integer', 0, 0, true],
    'tinyint with display width' => ['tinyint', 'tinyint(4)', 'integer', 4, 0, true],
    'native boolean' => ['boolean', 'boolean', 'boolean', 0, 0, false],
    'varchar' => ['varchar', 'varchar(80)', 'string', 80, 0, false],
    'char' => ['char', 'char(36)', 'string', 36, 0, false],
    'integer' => ['integer', 'integer', 'integer', 0, 0, true],
    'bigint' => ['bigint', 'bigint unsigned', 'integer', 0, 0, true],
    'float' => ['float', 'float', 'integer', 0, 0, true],
    'double' => ['double', 'double', 'integer', 0, 0, true],
    'decimal' => ['decimal', 'decimal(10,2)', 'decimal', 0, 2, true],
    'numeric' => ['numeric', 'numeric(12, 4)', 'decimal', 0, 4, true],
    'text' => ['text', 'text', 'textarea', 65_535, 0, false],
    'tinytext' => ['tinytext', 'tinytext', 'textarea', 255, 0, false],
    'mediumtext' => ['mediumtext', 'mediumtext', 'textarea', 16_777_215, 0, false],
    'longtext' => ['longtext', 'longtext', 'textarea', 4_294_967_295, 0, false],
    'json' => ['json', 'json', 'json', 0, 0, false],
    'jsonb' => ['jsonb', 'jsonb', 'json', 0, 0, false],
    'date' => ['date', 'date', 'date', 0, 0, false],
    'datetime' => ['datetime', 'datetime', 'datetime', 0, 0, false],
    'timestamp' => ['timestamp', 'timestamp', 'datetime', 0, 0, false],
    'unknown type' => ['custom_type', 'custom_type', 'custom_type', 0, 0, false],
]);

it('preserves nullable native columns', function (): void {
    $column = (new SchemaReader(Product::class))->normalize([
        'name' => 'optional',
        'type_name' => 'varchar',
        'type' => 'varchar(80)',
        'nullable' => true,
    ]);

    expect($column->getNotNull())->toBeFalse();
});
