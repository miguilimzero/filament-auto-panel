<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;

class FixtureApplication
{
    public readonly string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . '/auto-panel-optimize-' . bin2hex(random_bytes(8));
        $files = new Filesystem;

        foreach (['app', 'bootstrap/cache', 'config', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
            $files->ensureDirectoryExists($this->path($directory));
        }

        $files->copy(__DIR__ . '/bootstrap.php', $this->path('bootstrap/app.php'));
        $files->copy(__DIR__ . '/composer.json', $this->path('composer.json'));
    }

    public function path(string $path): string
    {
        return $this->root . '/' . $path;
    }

    public function command(string $command): array
    {
        $result = $this->run('command', $command);

        if ($result['exitCode'] !== 0) {
            throw new RuntimeException("Command [{$command}] failed:\n" . $result['output']);
        }

        return $result;
    }

    public function probe(string $mode = 'components'): array
    {
        return $this->run($mode);
    }

    public function cachedComponents(): array
    {
        return require $this->path('bootstrap/cache/filament/panels/optimization.php');
    }

    public function generatedArtifacts(): array
    {
        $paths = [];

        foreach ($this->cachedComponents()['livewireComponents'] as $class) {
            if (! str_starts_with($class, 'Miguilim\\FilamentAutoPanel\\Mounters\\Generated\\')) {
                continue;
            }

            $paths[$class] = $this->path('bootstrap/cache/filament/auto-panel/classes/' . class_basename($class) . '.php');
        }

        return $paths;
    }

    public function destroy(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
    }

    private function run(string $mode, ?string $command = null): array
    {
        $packageRoot = dirname(__DIR__, 3);
        $process = new Process(
            array_values(array_filter([PHP_BINARY, __DIR__ . '/run.php', $mode, $command], static fn ($value): bool => $value !== null)),
            $packageRoot,
            [
                'AUTO_PANEL_PACKAGE_ROOT' => $packageRoot,
                'AUTO_PANEL_APP_ROOT' => $this->root,
                'APP_BASE_PATH' => $this->root,
                'APP_RUNNING_IN_CONSOLE' => $mode === 'command' ? 'true' : 'false',
                'APP_ENV' => 'testing',
                'APP_CONFIG_CACHE' => $this->path('bootstrap/cache/config.php'),
                'APP_EVENTS_CACHE' => $this->path('bootstrap/cache/events.php'),
                'APP_ROUTES_CACHE' => $this->path('bootstrap/cache/routes.php'),
                'APP_SERVICES_CACHE' => $this->path('bootstrap/cache/services.php'),
                'APP_PACKAGES_CACHE' => $this->path('bootstrap/cache/packages.php'),
                'LARAVEL_STORAGE_PATH' => $this->path('storage'),
                'LARAVEL_BOOTSTRAP_PATH' => $this->path('bootstrap'),
            ],
        );
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Optimization fixture [{$mode}] failed:\n" . $process->getOutput() . $process->getErrorOutput());
        }

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
