<?php

use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

it('normalizes native schema metadata without Doctrine', function (string $typeName, string $type, string $expectedType, ?int $length, int $scale, bool $numeric): void {
    $column = new Column([
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
    'mysql boolean' => ['tinyint', 'tinyint(1)', 'boolean', null, 0, false],
    'unsigned mysql boolean' => ['tinyint', 'tinyint(1) unsigned', 'boolean', null, 0, false],
    'ordinary tinyint' => ['tinyint', 'tinyint', 'integer', null, 0, true],
    'tinyint with display width' => ['tinyint', 'tinyint(4)', 'integer', 4, 0, true],
    'native boolean' => ['boolean', 'boolean', 'boolean', null, 0, false],
    'postgres boolean' => ['bool', 'bool', 'boolean', null, 0, false],
    'varchar' => ['varchar', 'varchar(80)', 'string', 80, 0, false],
    'char' => ['char', 'char(36)', 'string', 36, 0, false],
    'postgres char' => ['bpchar', 'character(36)', 'string', 36, 0, false],
    'sql server string' => ['nvarchar', 'nvarchar(80)', 'string', 80, 0, false],
    'sql server unbounded string' => ['nvarchar', 'nvarchar(max)', 'textarea', null, 0, false],
    'unbounded string' => ['varchar', 'varchar', 'string', null, 0, false],
    'zero length' => ['varchar', 'varchar(0)', 'string', null, 0, false],
    'integer' => ['integer', 'integer', 'integer', null, 0, true],
    'bigint' => ['bigint', 'bigint unsigned', 'integer', null, 0, true],
    'float' => ['float', 'float', 'integer', null, 0, true],
    'double' => ['double', 'double', 'integer', null, 0, true],
    'decimal' => ['decimal', 'decimal(10,2)', 'decimal', null, 2, true],
    'numeric' => ['numeric', 'numeric(12, 4)', 'decimal', null, 4, true],
    'text' => ['text', 'text', 'textarea', null, 0, false],
    'tinytext without driver' => ['tinytext', 'tinytext', 'textarea', null, 0, false],
    'mediumtext without driver' => ['mediumtext', 'mediumtext', 'textarea', null, 0, false],
    'longtext without driver' => ['longtext', 'longtext', 'textarea', null, 0, false],
    'json' => ['json', 'json', 'json', null, 0, false],
    'jsonb' => ['jsonb', 'jsonb', 'json', null, 0, false],
    'date' => ['date', 'date', 'date', null, 0, false],
    'datetime' => ['datetime', 'datetime', 'datetime', null, 0, false],
    'timestamp' => ['timestamp', 'timestamp', 'datetime', null, 0, false],
    'postgres timestamp' => ['timestamptz', 'timestamp with time zone', 'datetime', null, 0, false],
    'unknown type' => ['custom_type', 'custom_type', 'custom_type', null, 0, false],
]);

it('preserves nullable native columns', function (): void {
    $column = new Column([
        'name' => 'optional',
        'type_name' => 'varchar',
        'type' => 'varchar(80)',
        'nullable' => true,
    ]);

    expect($column->getNotNull())->toBeFalse();
});

it('only infers text bounds for drivers with bounded text types', function (string $type, string $driver, ?int $length): void {
    $column = new Column(['name' => 'value', 'type_name' => $type, 'type' => $type, 'nullable' => true], driver: $driver);

    expect($column->getLength())->toBe($length);
})->with([
    ['tinytext', 'mysql', 255],
    ['text', 'mysql', 65_535],
    ['mediumtext', 'mariadb', 16_777_215],
    ['longtext', 'mariadb', 4_294_967_295],
    ['text', 'sqlite', null],
    ['text', 'pgsql', null],
]);

it('honors supported primitive casts without guessing custom casts', function (string $cast, string $expectedType, int $scale): void {
    $column = new Column([
        'name' => 'value',
        'type_name' => 'varchar',
        'type' => 'varchar(80)',
        'nullable' => true,
    ], $cast);

    expect($column->getType())->toBe($expectedType)
        ->and($column->getLength())->toBe(80)
        ->and($column->getDecimalPlaces())->toBe($scale);
})->with([
    ['boolean', 'boolean', 0],
    ['bool', 'boolean', 0],
    ['array', 'json', 0],
    ['json', 'json', 0],
    ['object', 'json', 0],
    ['collection', 'json', 0],
    ['encrypted:array', 'json', 0],
    ['encrypted:json', 'json', 0],
    ['encrypted:object', 'json', 0],
    ['encrypted:collection', 'json', 0],
    ['encrypted', 'string', 0],
    ['hashed', 'string', 0],
    ['integer', 'integer', 0],
    ['int', 'integer', 0],
    ['timestamp', 'integer', 0],
    ['float', 'integer', 0],
    ['double', 'integer', 0],
    ['real', 'integer', 0],
    ['decimal:3', 'decimal', 3],
    ['string', 'string', 0],
    ['date:Y-m-d', 'date', 0],
    ['immutable_date', 'date', 0],
    ['datetime:Y-m-d H:i:s', 'datetime', 0],
    ['immutable_datetime', 'datetime', 0],
    ['App\\Casts\\CustomCast', 'string', 0],
    ['App\\Enums\\Status', 'string', 0],
]);

it('identifies object casts without changing the array handler type', function (?string $cast, bool $object): void {
    $column = new Column(['name' => 'value', 'type_name' => 'json', 'type' => 'json', 'nullable' => true], $cast);

    expect($column->getType())->toBe('json')
        ->and($column->isObjectCast())->toBe($object);
})->with([
    [null, false],
    ['object', true],
    ['encrypted:object', true],
    ['array', false],
    ['json', false],
    ['collection', false],
    ['encrypted:array', false],
]);

it('lets a string cast override a numeric database type', function (): void {
    $column = new Column(['name' => 'value', 'type_name' => 'integer', 'type' => 'integer', 'nullable' => false], 'string');

    expect($column->getType())->toBe('string')->and($column->isNumeric())->toBeFalse();
});
