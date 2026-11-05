<?php

namespace Miguilim\FilamentAutoPanel;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoRelationManagerCommand;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoResourceCommand;
use Miguilim\FilamentAutoPanel\Mounters\RuntimeClassFactory;
use Miguilim\FilamentAutoPanel\Filament\RelationManagers\UserSessionsRelationManager;

class FilamentAutoPanelProvider extends ServiceProvider
{
    public function register(): void
    {
        RuntimeClassFactory::registerAutoloader();
    }

    public function boot()
    {
        Livewire::component('filament-auto-panel.user-sessions', UserSessionsRelationManager::class);

        $this->configureCommands();
    }

    protected function configureCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAutoRelationManagerCommand::class,
                MakeAutoResourceCommand::class,
            ]);
        }
    }
}
