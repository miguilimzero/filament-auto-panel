<?php

namespace Miguilim\FilamentAutoPanel\Commands;

use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;

use Filament\Commands\MakeResourceCommand;

class MakeAutoResourceCommand extends MakeResourceCommand
{
    use CanManipulateFiles;

    protected $description = 'Create a new Filament auto resource class';

    protected $name = 'make:filament-auto-resource';

    public function handle(): int
    {
        try {
            $this->configureModel();
            $this->configureRecordTitleAttribute();
            $this->configurePanel(question: 'Which panel would you like to create this resource in?');
            // $this->configureIsSimple();
            // $this->configureIsNested();
            // $this->configureCluster();
            $this->configureResourcesLocation(question: 'Which namespace would you like to create this resource in?');
            // $this->configureParentResource();
            // $this->configureHasViewOperation();
            // $this->configureIsGenerated();
            // $this->configureIsSoftDeletable();
            $this->configureHasResourceClassesOutsideDirectories();

            $this->configureLocation();
            // $this->configurePageRoutes();

            // $this->createFormSchema();
            // $this->createInfolistSchema();
            // $this->createTable();

            $this->createResourceClass();

            // $this->createManagePage();
            // $this->createListPage();
            // $this->createCreatePage();
            // $this->createEditPage();
            // $this->createViewPage();
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        $this->components->info("Filament resource created successfully.");

        if (empty($this->panel->getResourceNamespaces())) {
            $this->components->info('Make sure to register the resource with [resources()] or discover it with [discoverResources()] in the panel service provider.');
        }

        return static::SUCCESS;
    }

    protected function createResourceClass(): void
    {
        $realFqnEnd = substr($this->fqnEnd, strpos($this->fqnEnd, '\\') + 1);

        $path = (string) str("{$this->resourcesDirectory}\\{$realFqnEnd}.php")
            ->replace('\\', '/')
            ->replace('//', '/');

        if (! $this->option('force') && $this->checkForCollision($path)) {
            throw new FailureCommandOutput;
        }

        $recordTitleAttributeCode = '';
        if ($this->recordTitleAttribute) {
            $recordTitleAttributeCode = "\n\n    protected static string \$recordTitleAttribute = '{$this->recordTitleAttribute}';";
        }

        $this->copyStubToApp('AutoResource', $path, [
            'namespace' => substr($this->namespace, 0, strrpos($this->namespace, '\\')),
            'resourceClass' => $realFqnEnd,
            'model' => $this->modelFqn,
            'modelClass' => $this->modelFqnEnd,
            'recordTitleAttributeCode' => $recordTitleAttributeCode,
        ]);
    }
}
