<?php

use Filament\Forms\Components\TextInput;
use Filament\QueryBuilder\Constraints\Constraint;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Miguilim\FilamentAutoPanel\Filament\Filters\NumericComparisonOperator;

it('binds exact integer strings for every comparison and inverse', function (string $name, bool $inverse, string $comparison, int|string $number): void {
    $query = new Builder((new Connection(static fn (): PDO => new PDO('sqlite::memory:')))->table('records'));
    $operator = (new NumericComparisonOperator($name))->integer()->inverse($inverse)->settings(['number' => $number]);

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
    $query = new Builder((new Connection(static fn (): PDO => new PDO('sqlite::memory:')))->table('records'));
    $query->where('records.tenant_id', 7);
    $before = $query->getQuery()->wheres;
    $operator = (new NumericComparisonOperator('equals'))->integer()->settings(['number' => $number]);

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
        $operator = NumericComparisonOperator::make($name)
            ->integer()
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

it('binds exact fractional strings for every comparison and inverse', function (string $name, bool $inverse, string $comparison, int|string $number): void {
    $query = new Builder((new Connection(static fn (): PDO => new PDO('sqlite::memory:')))->table('records'));
    $operator = (new NumericComparisonOperator($name))->inverse($inverse)->settings(['number' => $number]);

    expect($operator->apply($query, 'records.amount'))->toBe($query)
        ->and($query->getQuery()->wheres[0]['column'])->toBe('records.amount')
        ->and($query->getQuery()->wheres[0]['operator'])->toBe($comparison)
        ->and($query->getBindings())->toBe([(string) $number]);
})->with([
    ['isMin', false, '>='], ['isMin', true, '<'],
    ['isMax', false, '<='], ['isMax', true, '>'],
    ['equals', false, '='], ['equals', true, '!='],
])->with([
    'forty significant digits' => '12345678901234567890.12345678901234567890',
    'adjacent fractional digit' => '12345678901234567890.12345678901234567891',
    'negative' => '-9007199254740993.00000000000000000001',
    'tiny' => '0.00000000000000000001',
    'trailing zeroes' => '-00012.3400',
    'positive sign' => '+12.3400',
    'no integer part' => '.125',
    'no fractional part' => '12.',
    'scientific' => '1.234567890123456789e-20',
    'positive expansion boundary' => '1e+10000',
    'negative expansion boundary' => '1e-10000',
    'padded exponent' => '1e+00010000',
    'padded zero exponent' => '1e-00000000000000000000',
    'integer zero' => 0,
    'native integer' => -12,
]);

it('ignores invalid fractional settings even when form validation is bypassed', function (mixed $number, string $name, bool $inverse): void {
    $query = new Builder((new Connection(static fn (): PDO => new PDO('sqlite::memory:')))->table('records'));
    $query->where('records.tenant_id', 7);
    $before = $query->getQuery()->wheres;
    $operator = (new NumericComparisonOperator($name))->inverse($inverse)->settings(['number' => $number]);

    expect($operator->apply($query, 'records.amount'))->toBe($query)
        ->and($query->getQuery()->wheres)->toBe($before)
        ->and($query->getBindings())->toBe([7]);
})->with([
    [null], [true], [false], [1.0], [1.5], [9007199254740993.0], [INF], [-INF], [NAN],
    [[]], [['12.34']], [new stdClass()],
    [new class implements Stringable {
        public function __toString(): string
        {
            return '12.34';
        }
    }],
    [''], [' '], ['-'], ['.'], ['not-a-number'], ['12,34'], ['1_000'], ['0x10'],
    ['１２.３４'], ['INF'], ['NAN'], ['1e'], ['1e+10001'], ['1e-10001'],
    ['1e00000010001'], ['1e-00000010001'], ['1e99999999999999999999999999'],
])->with([
    ['isMin', false], ['isMin', true], ['isMax', false], ['isMax', true], ['equals', false], ['equals', true],
]);

it('can switch between integer and fractional modes', function (): void {
    $operator = (new NumericComparisonOperator('equals'))->settings(['number' => '12.3400']);
    $query = new Builder((new Connection(static fn (): PDO => new PDO('sqlite::memory:')))->table('records'));

    expect($operator->isInteger())->toBeFalse();
    $operator->integer()->apply($query, 'records.amount');
    expect($operator->isInteger())->toBeTrue()
        ->and($query->getBindings())->toBe([]);

    $operator->integer(false)->apply($query, 'records.amount');
    expect($operator->isInteger())->toBeFalse()
        ->and($query->getBindings())->toBe(['12.3400']);
});
