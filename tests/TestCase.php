<?php

namespace Miguilim\FilamentAutoPanel\Tests;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\FilamentAutoPanelProvider;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $componentCacheDirectory;
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            \Zvizvi\UserFields\UserFieldsServiceProvider::class,
            FilamentAutoPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->componentCacheDirectory = $app->storagePath('framework/testing/auto-panel-cache-' . bin2hex(random_bytes(8)));
        $app['config']->set('filament.cache_path', $this->componentCacheDirectory);

        $driver = env('DB_CONNECTION', 'sqlite');

        $connection = [
            'driver' => $driver,
            'database' => env('DB_DATABASE', ':memory:'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $connection);
        $app['config']->set('database.connections.secondary', [
            ...$connection,
            'prefix' => 'secondary_',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('test')->path('admin')->resources([
            ProductResource::class,
        ]));

        Schema::create('owners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->nullable();
            $table->nullableMorphs('subject');
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->tinyInteger('quantity')->default(0);
            $table->decimal('price', 10, 2);
            $table->json('metadata')->nullable();
            $table->date('published_on')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        try {
            Schema::dropIfExists('products');
            Schema::dropIfExists('owners');
            Schema::connection('secondary')->dropIfExists('products');
        } finally {
            $this->app['files']->deleteDirectory($this->componentCacheDirectory);
            parent::tearDown();
        }
    }
}
