<?php

namespace Miguilim\FilamentAutoPanel\Mounters;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use ReflectionClass;

final class RuntimeClassFactory
{
    public static function registerAutoloader(): void
    {
        $loader = [self::class, 'autoload'];

        if (! in_array($loader, spl_autoload_functions(), true)) {
            spl_autoload_register($loader);
        }
    }

    public static function autoload(string $class): void
    {
        $prefix = __NAMESPACE__ . '\\Generated\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $name = substr($class, strlen($prefix));

        if (! preg_match('/\AC[a-f0-9]{64}\z/', $name) || class_exists($class, false)) {
            return;
        }

        $path = self::directory() . '/' . $name . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }

    /**
     * @template T of object
     * @param  class-string<T>  $expectedClass
     * @return class-string<T>
     */
    public static function resolveClass(string $class, string $expectedClass): string
    {
        if (! is_a($class, $expectedClass, true)) {
            throw new InvalidArgumentException("Class [{$class}] must extend [{$expectedClass}].");
        }

        /** @var class-string<T> $resolved */
        $resolved = (new ReflectionClass($class))->getName();

        return $resolved;
    }

    /**
     * @template T of object
     * @param  class-string<T>  $parent
     * @param  array<string, mixed>  $properties
     * @return class-string<T>
     */
    public static function make(string $parent, array $properties): string
    {
        $reflection = new ReflectionClass($parent);

        if ($reflection->isFinal() || $reflection->isAbstract()) {
            throw new InvalidArgumentException("Runtime class parent [{$parent}] must be concrete and non-final.");
        }

        $body = '';

        foreach ($properties as $declaration => $value) {
            $body .= $declaration . ' = ' . var_export($value, true) . ";\n";
        }

        $name = 'C' . hash('sha256', $parent . "\n" . $body);
        $namespace = __NAMESPACE__ . '\\Generated';
        /** @var class-string<T> $class */
        $class = $namespace . '\\' . $name;
        $directory = self::directory();
        $path = $directory . '/' . $name . '.php';

        // Cached routes and Livewire snapshots must resolve this class in later processes.
        if (! is_file($path)) {
            $files = new Filesystem();
            $files->ensureDirectoryExists($directory);
            $files->replace($path, "<?php\n\nnamespace {$namespace};\n\nclass {$name} extends \\{$parent}\n{\n{$body}}\n");
        }

        self::autoload($class);

        return $class;
    }

    private static function directory(): string
    {
        return rtrim(config('filament.cache_path') ?? base_path('bootstrap/cache/filament'), '/\\') . '/auto-panel/classes';
    }
}
