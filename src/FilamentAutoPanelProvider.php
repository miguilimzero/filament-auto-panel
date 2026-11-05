<?php

namespace Miguilim\FilamentAutoPanel;

use Illuminate\Support\ServiceProvider;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoRelationManagerCommand;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoResourceCommand;
use Miguilim\FilamentAutoPanel\Mounters\RuntimeClassFactory;

class FilamentAutoPanelProvider extends ServiceProvider
{
    public function register(): void
    {
        RuntimeClassFactory::registerAutoloader();
    }

    public function boot()
    {
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
