<?php

use Illuminate\Database\Connectors\ConnectorInterface;
use Miguilim\FilamentAutoPanel\Tests\Support\ParallelDatabase;
use Symfony\Component\Process\Process;

class ParallelDatabaseRecordingPdo extends PDO
{
    public array $statements = [];

    public bool $failCreate = false;

    public function __construct() {}

    public function exec(string $statement): int|false
    {
        $this->statements[] = $statement;

        if ($this->failCreate && str_starts_with($statement, 'CREATE DATABASE')) {
            throw new PDOException('Database already exists or CREATE permission denied.');
        }

        return 0;
    }
}

class ParallelDatabaseRecordingConnector implements ConnectorInterface
{
    public array $configurations = [];

    public readonly ParallelDatabaseRecordingPdo $pdo;

    public function __construct()
    {
        $this->pdo = new ParallelDatabaseRecordingPdo;
    }

    public function connect(array $config): PDO
    {
        $this->configurations[] = $config;

        return $this->pdo;
    }
}

function parallelDatabaseConfiguration(array $overrides = []): array
{
    return [
        'driver' => 'mysql',
        'database' => 'filament_auto_panel',
        'host' => '127.0.0.1',
        'port' => '3306',
        'username' => 'root',
        'password' => '',
        'prefix' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        ...$overrides,
    ];
}

it('derives bounded SQL-safe database names from the full base and worker identity', function (): void {
    $scope = new ParallelDatabase('1', 'run-a');
    $base = str_repeat('Base-` /Á', 30);
    $name = $scope->databaseName($base);

    expect($name)->toMatch('/\Afap_[a-z0-9_]+\z/')
        ->and(strlen($name))->toBeLessThanOrEqual(64)
        ->and($name)->not->toBe($base)
        ->and($scope->databaseName($base))->toBe($name)
        ->and($scope->databaseName($base . 'another'))->not->toBe($name)
        ->and((new ParallelDatabase('2', 'run-a'))->databaseName($base))->not->toBe($name)
        ->and((new ParallelDatabase('1', 'run-b'))->databaseName($base))->not->toBe($name);
});

it('leaves sequential connections completely unchanged', function (string $driver, string $database): void {
    $connector = new ParallelDatabaseRecordingConnector;
    $scope = new ParallelDatabase(null, 'unused', $connector);
    $connection = parallelDatabaseConfiguration(['driver' => $driver, 'database' => $database]);

    expect($scope->connection($connection))->toBe($connection)
        ->and($scope->databaseName($database))->toBe($database)
        ->and($connector->configurations)->toBe([]);

    $scope->cleanup();
    expect($connector->pdo->statements)->toBe([]);
})->with([
    ['sqlite', ':memory:'],
    ['sqlite', '/tmp/existing-tests.sqlite'],
    ['mysql', 'filament_auto_panel'],
    ['mariadb', 'filament_auto_panel'],
]);

it('leaves parallel in-memory SQLite connections untouched', function (): void {
    $connector = new ParallelDatabaseRecordingConnector;
    $scope = new ParallelDatabase('1', 'run-a', $connector);
    $connection = parallelDatabaseConfiguration(['driver' => 'sqlite', 'database' => ':memory:']);

    expect($scope->connection($connection))->toBe($connection)
        ->and($connector->configurations)->toBe([]);
});

it('rejects parallel file-backed SQLite before touching the file', function (string $database): void {
    $connector = new ParallelDatabaseRecordingConnector;
    $scope = new ParallelDatabase('1', 'run-a', $connector);

    expect(fn () => $scope->connection(parallelDatabaseConfiguration(['driver' => 'sqlite', 'database' => $database])))
        ->toThrow(RuntimeException::class, 'File-backed SQLite is not safe here')
        ->and($connector->configurations)->toBe([]);
})->with(['/tmp/existing-tests.sqlite', 'file:shared?mode=memory&cache=shared']);

it('creates once across app configurations and shares the database without changing connection prefixes or base environment', function (string $driver): void {
    $connector = new ParallelDatabaseRecordingConnector;
    $scope = new ParallelDatabase('1', 'run-a', $connector);
    $base = parallelDatabaseConfiguration(['driver' => $driver, 'charset' => 'utf8', 'collation' => 'utf8_unicode_ci']);
    $environment = [getenv('DB_DATABASE'), $_ENV, $_SERVER];
    $database = $scope->databaseName($base['database']);

    try {
        $testing = $scope->connection($base);
        $secondary = $scope->connection([...$base, 'prefix' => 'secondary_']);

        expect($scope->connection($base))->toBe($testing)
            ->and($testing)->toBe([...$base, 'database' => $database])
            ->and($secondary)->toBe([...$testing, 'prefix' => 'secondary_'])
            ->and($base['database'])->toBe('filament_auto_panel')
            ->and([getenv('DB_DATABASE'), $_ENV, $_SERVER])->toBe($environment)
            ->and($connector->configurations)->toBe([[...$base, 'database' => '']])
            ->and($connector->pdo->statements)->toBe([
                "CREATE DATABASE `{$database}` CHARACTER SET `utf8` COLLATE `utf8_unicode_ci`",
            ]);
    } finally {
        $scope->cleanup();
    }

    $scope->cleanup();
    expect($connector->pdo->statements)->toBe([
        "CREATE DATABASE `{$database}` CHARACTER SET `utf8` COLLATE `utf8_unicode_ci`",
        "DROP DATABASE `{$database}`",
    ]);
})->with(['mysql', 'mariadb']);

it('never claims or drops a database when CREATE fails', function (): void {
    $connector = new ParallelDatabaseRecordingConnector;
    $connector->pdo->failCreate = true;
    $scope = new ParallelDatabase('1', 'run-a', $connector);
    $base = parallelDatabaseConfiguration();
    $database = $scope->databaseName($base['database']);

    expect(fn () => $scope->connection($base))
        ->toThrow(RuntimeException::class, 'needs CREATE and DROP DATABASE privileges');

    $scope->cleanup();
    expect($connector->pdo->statements)->toBe([
        "CREATE DATABASE `{$database}` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci`",
    ]);
});

it('validates configured database charset and collation before connecting', function (string $key): void {
    $connector = new ParallelDatabaseRecordingConnector;
    $scope = new ParallelDatabase('1', 'run-a', $connector);

    expect(fn () => $scope->connection(parallelDatabaseConfiguration([$key => 'unsafe`name'])))
        ->toThrow(RuntimeException::class, 'simple SQL identifiers')
        ->and($connector->configurations)->toBe([]);
})->with(['charset', 'collation']);

it('keeps the process-local worker scope stable across repeated access', function (): void {
    $worker = ParallelDatabase::forWorker();

    expect(ParallelDatabase::forWorker())->toBe($worker)
        ->and(ParallelDatabase::forWorker()->databaseName('base'))->toBe($worker->databaseName('base'));
});

it('uses worker tokens without Laravel hooks and drops only its owned database at process exit', function (?string $token, ?string $uniqueToken): void {
    $process = new Process([PHP_BINARY, '-r', <<<'PHP'
        require 'vendor/autoload.php';
        $worker = \Miguilim\FilamentAutoPanel\Tests\Support\ParallelDatabase::forWorker();
        $name = $worker->databaseName('base');
        echo $name, "\n";
        $connector = new class implements \Illuminate\Database\Connectors\ConnectorInterface {
            public function connect(array $config): PDO {
                return new class extends PDO {
                    public function __construct() {}
                    public function exec(string $statement): int|false {
                        echo $statement, "\n";
                        return 0;
                    }
                };
            }
        };
        $scope = new \Miguilim\FilamentAutoPanel\Tests\Support\ParallelDatabase('1', 'probe', $connector);
        $scope->connection(['driver' => 'mysql', 'database' => 'base', 'prefix' => '']);
        PHP,
    ], dirname(__DIR__, 2), [
        'TEST_TOKEN' => $token ?? false,
        'UNIQUE_TEST_TOKEN' => $uniqueToken ?? false,
        'PARATEST' => '1',
        'LARAVEL_PARALLEL_TESTING' => false,
    ]);
    $process->setTimeout(10);
    $process->mustRun();
    $lines = explode("\n", trim($process->getOutput()));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toStartWith('fap_base_')
        ->and($lines[1])->toMatch('/\ACREATE DATABASE `(fap_base_[a-f0-9]{32})` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci`\z/')
        ->and($lines[2])->toBe('DROP DATABASE `' . explode('`', $lines[1])[1] . '`');

    $process->mustRun();
    expect(explode("\n", trim($process->getOutput()))[0])->not->toBe($lines[0]);
})->with([
    'TEST_TOKEN fallback' => ['1', null],
    'UNIQUE_TEST_TOKEN only' => [null, 'unique-worker'],
    'both worker tokens' => ['1', 'unique-worker'],
]);

it('rejects ParaTest workers with disabled tokens instead of sharing the base', function (): void {
    $process = new Process([
        PHP_BINARY, '-r',
        "require 'vendor/autoload.php'; \\Miguilim\\FilamentAutoPanel\\Tests\\Support\\ParallelDatabase::forWorker();",
    ], dirname(__DIR__, 2), [
        'PARATEST' => '1',
        'TEST_TOKEN' => false,
        'UNIQUE_TEST_TOKEN' => false,
    ]);
    $process->setTimeout(10);
    $process->run();

    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput())->toContain('Do not use --no-test-tokens');
});

it('does not touch a configured SQLite base when isolation fails during Testbench setup', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'fap-base-');
    $pdo = new PDO('sqlite:' . $path);
    $pdo->exec('CREATE TABLE products (name TEXT)');
    $pdo->exec("INSERT INTO products VALUES ('base sentinel')");
    $pdo->exec('CREATE TABLE owners (name TEXT)');

    try {
        $process = new Process([
            PHP_BINARY, 'vendor/bin/pest', 'tests/Unit/ParallelDatabaseEnvironmentTest.php',
        ], dirname(__DIR__, 2), [
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $path,
            'TEST_TOKEN' => 'failure-probe',
            'UNIQUE_TEST_TOKEN' => false,
            'PARATEST' => false,
            'LARAVEL_PARALLEL_TESTING' => false,
        ]);
        $process->setTimeout(20);
        $process->run();

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getOutput())->toContain('File-backed SQLite is not safe here')
            ->and($pdo->query('SELECT name FROM products')->fetchColumn())->toBe('base sentinel')
            ->and($pdo->query('SELECT COUNT(*) FROM owners')->fetchColumn())->toBe(0);
    } finally {
        $pdo = null;
        unlink($path);
    }
});
