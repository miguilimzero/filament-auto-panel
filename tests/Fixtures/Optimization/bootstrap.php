<?php

use Illuminate\Foundation\Application;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization\FixtureServiceProvider;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization\OptimizationPanelProvider;

return Application::configure(basePath: getenv('AUTO_PANEL_APP_ROOT'))
    ->withProviders([
        FixtureServiceProvider::class,
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
        \Miguilim\FilamentAutoPanel\FilamentAutoPanelProvider::class,
        OptimizationPanelProvider::class,
    ], withBootstrapProviders: false)
    ->withRouting(using: static function (): void {})
    ->withMiddleware()
    ->withExceptions()
    ->create();
