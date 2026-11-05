<?php

namespace Miguilim\FilamentAutoPanel\Tests\Support;

use Illuminate\Database\Connectors\ConnectorInterface;
use Illuminate\Database\Connectors\MySqlConnector;
use RuntimeException;
use Throwable;

final class ParallelDatabase
{
    private static ?self $worker = null;

    private array $ownedDatabases = [];

    private bool $cleanupRegistered = false;

    public function __construct(
        private readonly ?string $token,
        private readonly string $uniqueToken,
        private readonly ConnectorInterface $connector = new MySqlConnector,
    ) {}

    public static function forWorker(): self
    {
        if (self::$worker === null) {
            $uniqueToken = self::environment('UNIQUE_TEST_TOKEN');
            $token = self::environment('TEST_TOKEN') ?? $uniqueToken;

            if ($token === null && self::environment('PARATEST') === '1') {
                throw new RuntimeException('Parallel database isolation requires TEST_TOKEN or UNIQUE_TEST_TOKEN. Do not use --no-test-tokens.');
            }

            self::$worker = new self($token, ($uniqueToken ?? '') . bin2hex(random_bytes(16)));
        }

        return self::$worker;
    }

    public function databaseName(string $base): string
    {
        if ($this->token === null) {
            return $base;
        }

        $label = substr((string) preg_replace('/[^a-z0-9_]/', '_', strtolower($base)), 0, 27);
        $hash = substr(hash('sha256', serialize([$base, $this->token, $this->uniqueToken])), 0, 32);

        return 'fap_' . $label . '_' . $hash;
    }

    public function connection(array $connection): array
    {
        if ($this->token === null) {
            return $connection;
        }

        if ($connection['driver'] === 'sqlite' && $connection['database'] === ':memory:') {
            return $connection;
        }

        if (! in_array($connection['driver'], ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Parallel tests support only SQLite :memory: or isolated MySQL/MariaDB databases. File-backed SQLite is not safe here; use DB_DATABASE=:memory: or run vendor/bin/pest sequentially.');
        }

        // Prefixes belong to the connections, not the worker database identity.
        $identity = $connection;
        unset($identity['prefix']);
        $key = hash('sha256', serialize($identity));

        if (! isset($this->ownedDatabases[$key])) {
            $base = (string) $connection['database'];
            $database = $this->databaseName($base);

            if ($database === $base) {
                throw new RuntimeException('Refusing to use the configured base database for a parallel worker.');
            }

            $charset = $connection['charset'] ?? 'utf8mb4';
            $collation = $connection['collation'] ?? 'utf8mb4_unicode_ci';

            foreach ([$charset, $collation] as $identifier) {
                if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier)) {
                    throw new RuntimeException('Parallel database charset and collation must be simple SQL identifiers.');
                }
            }

            try {
                // Connect without selecting (or ever modifying) the configured base database.
                $pdo = $this->connector->connect([...$connection, 'database' => '']);
                $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET `{$charset}` COLLATE `{$collation}`");
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Could not create isolated parallel database [{$database}]. The MySQL/MariaDB user needs CREATE and DROP DATABASE privileges for fap_* worker databases. The base database [{$base}] was not modified.",
                    previous: $exception,
                );
            }

            // No IF NOT EXISTS: only a successful CREATE grants ownership for cleanup.
            $this->ownedDatabases[$key] = ['database' => $database, 'pdo' => $pdo];

            if (! $this->cleanupRegistered) {
                register_shutdown_function($this->cleanup(...));
                $this->cleanupRegistered = true;
            }
        }

        return [...$connection, 'database' => $this->ownedDatabases[$key]['database']];
    }

    public function cleanup(): void
    {
        foreach ($this->ownedDatabases as $key => ['database' => $database, 'pdo' => $pdo]) {
            try {
                $pdo->exec("DROP DATABASE `{$database}`");
                unset($this->ownedDatabases[$key]);
            } catch (Throwable $exception) {
                fwrite(STDERR, "Could not drop owned parallel database [{$database}]. Grant DROP DATABASE permission and remove it manually: {$exception->getMessage()}\n");
            }
        }
    }

    private static function environment(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return $value === false || $value === '' ? null : (string) $value;
    }
}
