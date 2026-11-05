<?php

use Illuminate\Filesystem\Filesystem;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;
use Miguilim\FilamentAutoPanel\Mounters\RuntimeClassFactory;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

it('recreates a missing artifact even when its class is already loaded', function (): void {
    $class = PageMounter::makeList(ProductResource::class)->getPage();
    $path = config('filament.cache_path') . '/auto-panel/classes/' . class_basename($class) . '.php';
    $definition = file_get_contents($path);
    unlink($path);

    expect(class_exists($class, false))->toBeTrue()
        ->and(is_file($path))->toBeFalse()
        ->and(PageMounter::makeList(ProductResource::class)->getPage())->toBe($class)
        ->and(file_get_contents($path))->toBe($definition);
});

it('persists loaded classes to the currently configured Filament cache directory', function (): void {
    $class = PageMounter::makeList(ProductResource::class)->getPage();
    $original = config('filament.cache_path');
    $relocated = $original . '/relocated';

    try {
        config()->set('filament.cache_path', $relocated);

        expect(PageMounter::makeList(ProductResource::class)->getPage())->toBe($class)
            ->and(is_file($relocated . '/auto-panel/classes/' . class_basename($class) . '.php'))->toBeTrue();
    } finally {
        config()->set('filament.cache_path', $original);
    }
});

it('registers its generated-class autoloader only once', function (): void {
    RuntimeClassFactory::registerAutoloader();
    RuntimeClassFactory::registerAutoloader();

    expect(array_filter(spl_autoload_functions(), fn ($loader): bool => $loader === [RuntimeClassFactory::class, 'autoload']))->toHaveCount(1);
});

it('does not include arbitrary files through malformed generated class names', function (): void {
    $files = new Filesystem();
    $directory = config('filament.cache_path') . '/auto-panel/classes';
    $files->ensureDirectoryExists($directory);
    $files->put($directory . '/sentinel.php', '<?php throw new RuntimeException("Invalid class name was included");');
    $files->put(dirname($directory) . '/guard.php', '<?php throw new RuntimeException("Directory traversal was included");');

    foreach (['sentinel', '../guard', 'C' . str_repeat('g', 64), 'C' . str_repeat('a', 64) . '\\sentinel'] as $name) {
        RuntimeClassFactory::autoload('Miguilim\\FilamentAutoPanel\\Mounters\\Generated\\' . $name);
    }

    expect(class_exists('Miguilim\\FilamentAutoPanel\\Mounters\\Generated\\sentinel', false))->toBeFalse();
});
