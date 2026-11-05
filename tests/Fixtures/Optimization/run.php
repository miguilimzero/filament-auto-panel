<?php

use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization\OwnerResource;

require getenv('AUTO_PANEL_PACKAGE_ROOT') . '/vendor/autoload.php';

try {
    $app = require getenv('AUTO_PANEL_APP_ROOT') . '/bootstrap/app.php';
    $mode = $argv[1];

    if ($mode === 'command') {
        $kernel = $app->make(ConsoleKernel::class);
        $exitCode = $kernel->call($argv[2], ['--no-interaction' => true]);
        $result = [
            'exitCode' => $exitCode,
            'output' => $kernel->output(),
            'console' => $app->runningInConsole(),
        ];
    } else {
        $app->instance('request', Request::create('http://localhost/admin/owners'));
        $app->make(HttpKernel::class)->bootstrap();
        $panel = Filament::getPanel('optimization');
        $result = [
            'console' => $app->runningInConsole(),
            'componentsCached' => $panel->hasCachedComponents(),
            'routesCached' => $app->routesAreCached(),
            'components' => [],
        ];

        if ($mode === 'routes') {
            foreach (['index', 'view', 'custom'] as $page) {
                $route = $app['router']->getRoutes()->getByName('filament.optimization.resources.owners.' . $page);

                if ($route === null) {
                    throw new RuntimeException("Missing cached resource route [{$page}].");
                }

                $controller = $route->getController();
                $result['components'][$page] = [
                    'class' => $controller::class,
                    'resource' => $controller::getResource(),
                    'uri' => $route->uri(),
                ];
            }
        } elseif ($mode === 'components') {
            $cache = require $panel->getComponentCachePath();
            $generated = array_filter(
                $cache['livewireComponents'],
                static fn (string $class): bool => str_starts_with($class, 'Miguilim\\FilamentAutoPanel\\Mounters\\Generated\\'),
            );
            $result['loadedBeforeResolution'] = array_values(array_filter(
                $generated,
                static fn (string $class): bool => class_exists($class, false),
            ));

            foreach ($generated as $name => $class) {
                // Resolve the cached name first: checking class_exists() here would preload it.
                $component = Livewire::new($name);
                $properties = (new ReflectionClass($component))->getStaticProperties();
                $result['components'][$name] = [
                    'class' => $component::class,
                    'name' => $component->getName(),
                    'parent' => get_parent_class($component),
                    'resource' => $component instanceof Page ? $component::getResource() : null,
                    'relatedResource' => $component instanceof AutoRelationManager ? $component::getRelatedResource() : null,
                    'relationship' => $component instanceof AutoRelationManager ? $component::getRelationshipName() : null,
                    'properties' => array_intersect_key($properties, array_flip([
                        'visibleColumns', 'searchableColumns', 'enumDictionary', 'recordTitleAttribute',
                        'associateAttachActions', 'intrusive', 'readOnly',
                    ])),
                ];

                if ($component::class !== $class) {
                    throw new RuntimeException("Cached component [{$name}] resolved to a different class.");
                }
            }
        } else {
            throw new InvalidArgumentException("Unknown probe [{$mode}].");
        }

        $result['pageCalls'] = OwnerResource::$pageCalls;
        $result['relationCalls'] = OwnerResource::$relationCalls;
    }

    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
}
