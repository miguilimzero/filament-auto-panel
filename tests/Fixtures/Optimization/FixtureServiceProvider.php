<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization;

use Illuminate\Support\ServiceProvider;

class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app['config']->set([
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.debug' => true,
            'app.url' => 'http://localhost',
            'filament.cache_path' => $this->app->bootstrapPath('cache/filament'),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'logging.default' => 'stderr',
            'view.compiled' => $this->app->storagePath('framework/views'),
        ]);
    }
}
