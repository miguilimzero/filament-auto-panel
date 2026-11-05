<?php

use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\Tests\Support\ParallelDatabase;
use Miguilim\FilamentAutoPanel\Tests\TestCase;

uses(TestCase::class);

// Exercise feature-style cleanup even when Testbench application creation fails.
afterEach(function (): void {
    Schema::dropIfExists('products');
});

it('configures both test connections from the unchanged base on every application boot', function (int $boot): void {
    $base = env('DB_DATABASE', ':memory:');
    $testing = config('database.connections.testing');
    $secondary = config('database.connections.secondary');
    $expected = in_array($testing['driver'], ['mysql', 'mariadb'], true)
        ? ParallelDatabase::forWorker()->databaseName($base)
        : $base;

    expect(config('database.default'))->toBe('testing')
        ->and($testing['database'])->toBe($expected)
        ->and($testing['prefix'])->toBe('')
        ->and($secondary)->toBe([...$testing, 'prefix' => 'secondary_'])
        ->and(env('DB_DATABASE', ':memory:'))->toBe($base)
        ->and($this->app['db']->connection('testing')->getSchemaBuilder()->hasTable('products'))->toBeTrue();
})->with([1, 2]);

it('disconnects every established connection before Testbench destroys the application', function (): void {
    $database = $this->app['db'];
    config(['database.connections.parallel_database_extra' => config('database.connections.testing')]);
    $connections = [
        $database->connection('testing'),
        $database->connection('secondary'),
        $database->connection('parallel_database_extra'),
    ];

    foreach ($connections as $connection) {
        $connection->setReadPdo($connection->getPdo());
        expect($connection->getRawPdo())->toBeInstanceOf(PDO::class)
            ->and($connection->getRawReadPdo())->toBeInstanceOf(PDO::class);
    }

    $this->beforeApplicationDestroyed(static function () use ($connections): void {
        foreach ($connections as $connection) {
            expect($connection->getRawPdo())->toBeNull()
                ->and($connection->getRawReadPdo())->toBeNull();
        }
    });
});

it('does not establish an unused secondary connection during teardown', function (): void {
    $database = $this->app['db'];
    $connections = $database->getConnections();

    expect($connections)->toHaveKey('testing')->not->toHaveKey('secondary');

    $this->beforeApplicationDestroyed(static function () use ($database, $connections): void {
        expect(array_keys($database->getConnections()))->toBe(array_keys($connections));

        foreach ($connections as $connection) {
            expect($connection->getRawPdo())->toBeNull();
        }
    });
});
