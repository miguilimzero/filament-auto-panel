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

        if (getenv('AUTO_PANEL_USER_SESSIONS') === 'true') {
            $this->app['config']->set([
                'session.driver' => 'database',
                'session.connection' => 'unavailable_sessions',
                'session.table' => 'optimization_sessions',
                'database.connections.unavailable_sessions' => [
                    'driver' => 'sqlite',
                    'database' => $this->app->storagePath('missing-sessions.sqlite'),
                    'prefix' => '',
                ],
                'auth.defaults.guard' => 'web',
                'auth.guards' => [
                    'web' => ['driver' => 'session', 'provider' => 'optimization_users'],
                ],
                'auth.providers' => [
                    'optimization_users' => ['driver' => 'eloquent', 'model' => User::class],
                ],
            ]);
        }
    }
}
