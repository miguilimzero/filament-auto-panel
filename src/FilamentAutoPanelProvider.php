<?php

namespace Miguilim\FilamentAutoPanel;

use Miguilim\FilamentAutoPanel\Commands\MakeAutoRelationManagerCommand;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoResourceCommand;
use Illuminate\Support\ServiceProvider;

class FilamentAutoPanelProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureCommands();
    }

    /**
     * Configure the commands offered by the application.
     */
    protected function configureCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeAutoRelationManagerCommand::class,
            MakeAutoResourceCommand::class,
        ]);
    }
}