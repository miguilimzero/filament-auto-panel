<?php

use Filament\Forms\Components\TextInput;
use Filament\QueryBuilder\Constraints\Constraint;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Miguilim\FilamentAutoPanel\Filament\Filters\IntegerComparisonOperator;

it('binds exact integer strings for every comparison and inverse', function (string $name, bool $inverse, string $comparison, int|string $number): void {
    $query = new Builder((new Connection(null))->table('records'));
    $operator = (new IntegerComparisonOperator($name))->inverse($inverse)->settings(['number' => $number]);

    expect($operator->apply($query, 'records.id'))->toBe($query)
        ->and($query->getQuery()->wheres[0]['column'])->toBe('records.id')
        ->and($query->getQuery()->wheres[0]['operator'])->toBe($comparison)
        ->and($query->getBindings())->toBe([(string) $number]);
})->with([
    ['isMin', false, '>='],
    ['isMin', true, '<'],
    ['isMax', false, '<='],
    ['isMax', true, '>'],
    ['equals', false, '='],
    ['equals', true, '!='],
])->with([
    'above floating-point precision' => '9007199254740993',
    'unsigned bigint maximum' => '18446744073709551615',
    'below signed bigint minimum' => '-9223372036854775809',
    'leading zeroes' => '-00012',
    'string zero' => '0',
    'integer zero' => 0,
    'negative integer' => -12,
]);

it('ignores malformed integer settings without changing the query', function (mixed $number): void {
    $query = new Builder((new Connection(null))->table('records'));
    $query->where('records.tenant_id', 7);
    $before = $query->getQuery()->wheres;
    $operator = (new IntegerComparisonOperator('equals'))->settings(['number' => $number]);

    expect($operator->apply($query, 'records.id'))->toBe($query)
        ->and($query->getQuery()->wheres)->toBe($before)
        ->and($query->getBindings())->toBe([7]);
})->with([
    [null], [true], [false], [1.0], [1.5], [[]], [['12']], [new stdClass()],
    [''], ['-'], ['+12'], ['1e3'], ['1.0'], [' 12'], ['12 '], ["12\n"], ['１２'],
]);

it('uses native translations and one string-preserving input', function (string $name, string $key, bool $inverse): void {
    $previousContainer = Container::getInstance();
    $container = new Container();
    $loader = new ArrayLoader();
    $messages = require __DIR__ . '/../../vendor/filament/query-builder/resources/lang/en/query-builder.php';
    $loader->addMessages('en', 'query-builder', $messages, 'filament-query-builder');
    $container->instance('translator', new Translator($loader, 'en'));
    Container::setInstance($container);

    try {
        $number = '18446744073709551615';
        $direction = $inverse ? 'inverse' : 'direct';
        $operator = IntegerComparisonOperator::make($name)
            ->constraint((new Constraint('id'))->attributeLabel('Record identifier'))
            ->settings(['number' => $number])
            ->inverse($inverse);
        $translations = $messages['operators']['number'][$key];
        $schema = $operator->getFormSchema();

        expect($operator->getLabel())->toBe($translations['label'][$direction])
            ->and($operator->getSummary())->toBe(strtr($translations['summary'][$direction], [
                ':attribute' => 'Record identifier', ':number' => $number,
            ]))
            ->and($schema)->toHaveCount(1)
            ->and($schema[0])->toBeInstanceOf(TextInput::class)
            ->and($schema[0]->getName())->toBe('number')
            ->and($schema[0]->getLabel())->toBe('Number')
            ->and($schema[0]->getInputMode())->toBe('numeric')
            ->and($schema[0]->getRegexPattern())->toBe('/^-?[0-9]+$/D')
            ->and($schema[0]->isRequired())->toBeTrue()
            ->and($schema[0]->isNumeric())->toBeFalse()
            ->and($schema[0]->isInteger())->toBeFalse()
            ->and($schema[0]->getDefaultStateCasts())->toBe([]);
    } finally {
        Container::setInstance($previousContainer);
    }
})->with([
    ['isMin', 'is_min', false], ['isMin', 'is_min', true],
    ['isMax', 'is_max', false], ['isMax', 'is_max', true],
    ['equals', 'equals', false], ['equals', 'equals', true],
]);
