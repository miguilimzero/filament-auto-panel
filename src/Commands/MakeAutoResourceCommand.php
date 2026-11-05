<?php

namespace Miguilim\FilamentAutoPanel\Commands;

use Filament\Commands\MakeResourceCommand;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;

class MakeAutoResourceCommand extends MakeResourceCommand
{
    protected $description = 'Create a new Filament auto resource class';

    protected $name = 'make:filament-auto-resource';

    protected $aliases = [];

    public function handle(): int
    {
        try {
            $this->configureModel();
            $this->configureRecordTitleAttribute();
            $this->configurePanel(question: 'Which panel would you like to create this resource in?');
            $this->configureResourcesLocation(question: 'Which namespace would you like to create this resource in?');
            $this->configureHasResourceClassesOutsideDirectories();
            $this->configureLocation();
            $this->createResourceClass();
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        $this->components->info('Filament resource created successfully.');

        if (empty($this->panel->getResourceNamespaces())) {
            $this->components->info('Make sure to register the resource with [resources()] or discover it with [discoverResources()] in the panel service provider.');
        }

        return static::SUCCESS;
    }

    protected function configureLocation(): void
    {
        // Auto resources need a single class, not Filament's generated page/schema directories.
        $this->fqnEnd = "{$this->modelFqnEnd}Resource";
        $this->fqn = "{$this->resourcesNamespace}\\{$this->fqnEnd}";
        $this->namespace = (string) str($this->fqn)->beforeLast('\\');
        $this->directory = dirname(str_replace('\\', '/', rtrim($this->resourcesDirectory, '/\\') . "/{$this->fqnEnd}.php"));
    }

    protected function createResourceClass(): void
    {
        $path = str_replace('\\', '/', rtrim($this->resourcesDirectory, '/\\') . "/{$this->fqnEnd}.php");

        if (! $this->option('force') && $this->checkForCollision($path)) {
            throw new FailureCommandOutput;
        }

        $recordTitleAttributeCode = '';

        if (filled($this->recordTitleAttribute)) {
            $recordTitleAttributeCode = "\n\n    protected static ?string \$recordTitleAttribute = " . var_export($this->recordTitleAttribute, true) . ';';
        }

        $this->copyStubToApp('AutoResource', $path, [
            'namespace' => $this->namespace,
            'resourceClass' => class_basename($this->fqn),
            'model' => $this->modelFqn,
            'modelClass' => class_basename($this->modelFqn),
            'recordTitleAttributeCode' => $recordTitleAttributeCode,
        ]);
    }

    protected function getDefaultStubPath(): string
    {
        return dirname(__DIR__, 2) . '/stubs';
    }
}
