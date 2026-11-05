<?php

use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceListRecords;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceViewRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization\CustomOwners;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization\FixtureApplication;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization\OwnerResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;

beforeEach(function (): void {
    $this->optimizationApp = new FixtureApplication;
});

afterEach(function (): void {
    $this->optimizationApp->destroy();
});

it('resolves optimized pages and configured managers in a cold HTTP process without replaying relations', function (): void {
    $warm = $this->optimizationApp->command('filament:optimize');

    expect($warm['console'])->toBeTrue()
        ->and(is_file($this->optimizationApp->path('bootstrap/cache/filament/panels/optimization.php')))->toBeTrue()
        ->and(is_file($this->optimizationApp->path('bootstrap/cache/blade-icons.php')))->toBeTrue();

    $cold = $this->optimizationApp->probe();

    expect($cold['console'])->toBeFalse()
        ->and($cold['componentsCached'])->toBeTrue()
        ->and($cold['routesCached'])->toBeFalse()
        ->and($cold['relationCalls'])->toBe(0);

    assertOptimizationComponents($cold);
});

it('resolves all cached resource route controllers in a cold HTTP process with both native caches enabled', function (): void {
    $this->optimizationApp->command('filament:optimize');
    $this->optimizationApp->command('route:cache');

    expect(is_file($this->optimizationApp->path('bootstrap/cache/filament/panels/optimization.php')))->toBeTrue()
        ->and(is_file($this->optimizationApp->path('bootstrap/cache/routes.php')))->toBeTrue();

    $cold = $this->optimizationApp->probe('routes');

    expect($cold['console'])->toBeFalse()
        ->and($cold['componentsCached'])->toBeTrue()
        ->and($cold['routesCached'])->toBeTrue()
        ->and($cold['pageCalls'])->toBe(0)
        ->and($cold['relationCalls'])->toBe(0)
        ->and(array_keys($cold['components']))->toBe(['index', 'view', 'custom'])
        ->and(array_column($cold['components'], 'resource'))->toBe(array_fill(0, 3, OwnerResource::class))
        ->and(array_column($cold['components'], 'uri'))->toBe(['admin/owners', 'admin/owners/{record}', 'admin/owners/custom']);

    $cached = $this->optimizationApp->cachedComponents()['livewireComponents'];

    foreach ($cold['components'] as $component) {
        expect($cached)->toContain($component['class']);
    }
});

it('resolves cached Livewire names before any page or relation factory runs with routes cached', function (): void {
    $this->optimizationApp->command('route:cache');
    $this->optimizationApp->command('filament:optimize');

    expect(is_file($this->optimizationApp->path('bootstrap/cache/filament/panels/optimization.php')))->toBeTrue()
        ->and(is_file($this->optimizationApp->path('bootstrap/cache/routes.php')))->toBeTrue();

    $cold = $this->optimizationApp->probe();

    expect($cold['console'])->toBeFalse()
        ->and($cold['componentsCached'])->toBeTrue()
        ->and($cold['routesCached'])->toBeTrue()
        ->and($cold['loadedBeforeResolution'])->toBe([])
        ->and($cold['pageCalls'])->toBe(0)
        ->and($cold['relationCalls'])->toBe(0);

    assertOptimizationComponents($cold);
});

it('retains generated artifacts when Filament is cleared with routes cached and supports warming again', function (): void {
    $this->optimizationApp->command('filament:optimize');
    $this->optimizationApp->command('route:cache');
    $artifacts = $this->optimizationApp->generatedArtifacts();

    expect($artifacts)->toHaveCount(5);

    foreach ($artifacts as $path) {
        expect(is_file($path))->toBeTrue();
    }

    $routes = file_get_contents($this->optimizationApp->path('bootstrap/cache/routes.php'));
    $this->optimizationApp->command('filament:optimize-clear');

    expect(is_file($this->optimizationApp->path('bootstrap/cache/filament/panels/optimization.php')))->toBeFalse()
        ->and(is_file($this->optimizationApp->path('bootstrap/cache/blade-icons.php')))->toBeFalse()
        ->and(file_get_contents($this->optimizationApp->path('bootstrap/cache/routes.php')))->toBe($routes);

    foreach ($artifacts as $path) {
        expect(is_file($path))->toBeTrue();
    }

    $cleared = $this->optimizationApp->probe('routes');

    expect($cleared['console'])->toBeFalse()
        ->and($cleared['componentsCached'])->toBeFalse()
        ->and($cleared['routesCached'])->toBeTrue()
        ->and($cleared['components'])->toHaveCount(3);

    $this->optimizationApp->command('filament:optimize');

    expect(is_file($this->optimizationApp->path('bootstrap/cache/filament/panels/optimization.php')))->toBeTrue()
        ->and(is_file($this->optimizationApp->path('bootstrap/cache/blade-icons.php')))->toBeTrue()
        ->and($this->optimizationApp->generatedArtifacts())->toBe($artifacts);

    $rewarmed = $this->optimizationApp->probe();

    expect($rewarmed['componentsCached'])->toBeTrue()
        ->and($rewarmed['routesCached'])->toBeTrue()
        ->and($rewarmed['pageCalls'])->toBe(0)
        ->and($rewarmed['relationCalls'])->toBe(0);

    assertOptimizationComponents($rewarmed);
});

it('loads optimized components from read-only generated class files', function (): void {
    $this->optimizationApp->command('filament:optimize');
    $artifacts = $this->optimizationApp->generatedArtifacts();
    $directory = dirname(reset($artifacts));

    try {
        foreach ($artifacts as $path) {
            chmod($path, 0444);
        }
        chmod($directory, 0555);

        $cold = $this->optimizationApp->probe();

        expect($cold['componentsCached'])->toBeTrue()
            ->and($cold['relationCalls'])->toBe(0);
        assertOptimizationComponents($cold);
    } finally {
        chmod($directory, 0755);
        foreach ($artifacts as $path) {
            chmod($path, 0644);
        }
    }
});

function assertOptimizationComponents(array $result): void
{
    expect($result['components'])->toHaveCount(5);
    $pages = [];
    $managers = [];

    foreach ($result['components'] as $name => $component) {
        expect($component['name'])->toBe($name)
            ->and($component['class'])->toMatch('/\\\\Generated\\\\C[a-f0-9]{64}$/');

        if ($component['resource'] !== null) {
            expect($component['resource'])->toBe(OwnerResource::class);
            $pages[] = $component['parent'];
        } else {
            expect($component['parent'])->toBe(AutoRelationManager::class)
                ->and($component['relationship'])->toBe('products')
                ->and($component['properties']['associateAttachActions'])->toBeFalse();
            $managers[] = $component;
        }
    }

    expect($pages)->toBe([AutoResourceListRecords::class, AutoResourceViewRecord::class, CustomOwners::class])
        ->and($managers)->toHaveCount(2)
        ->and($managers[0]['relatedResource'])->toBe(ProductResource::class)
        ->and($managers[1]['relatedResource'])->toBeNull()
        ->and($managers[1]['properties'])->toMatchArray([
            'visibleColumns' => ['name', 'status'],
            'searchableColumns' => ['name' => 'both'],
            'enumDictionary' => ['status' => ['draft' => ["Owner's \\ draft", 'gray']]],
            'recordTitleAttribute' => 'name',
            'intrusive' => false,
            'readOnly' => true,
        ]);
}
