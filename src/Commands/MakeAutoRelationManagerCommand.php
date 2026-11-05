<?php

namespace Miguilim\FilamentAutoPanel\Commands;

use Filament\Commands\MakeRelationManagerCommand;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;

class MakeAutoRelationManagerCommand extends MakeRelationManagerCommand
{
    protected $description = 'Create a new Filament auto relation manager class';

    protected $name = 'make:filament-auto-relation-manager';

    protected $aliases = [];

    public function handle(): int
    {
        try {
            $this->configurePanel(question: 'Which panel would you like to create this relation manager in?');
            $this->configureResource();
            $this->configureRelationship();
            $this->recordTitleAttribute = $this->option('record-title-attribute') ?? $this->argument('recordTitleAttribute');
            $this->configureLocation();
            $this->createRelationManager();
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        $this->components->info('Filament relation manager created successfully.');
        $this->components->info("Make sure to register the relation in [{$this->resourceFqn}::getRelations()].");

        return static::SUCCESS;
    }

    protected function configureLocation(): void
    {
        $resourceClass = class_basename($this->resourceFqn);
        $resourceName = str_ends_with($resourceClass, 'Resource') ? substr($resourceClass, 0, -8) : $resourceClass;
        $className = $resourceName . str($this->relationship)->studly() . 'RelationManager';

        $namespace = (string) preg_replace('/(^|\\\\)Resources(?=\\\\|$)/', '${1}RelationManagers', $this->resourcesNamespace);
        $directory = (string) preg_replace('~(^|/)Resources(?=/|$)~', '${1}RelationManagers', str_replace('\\', '/', $this->resourcesDirectory));

        if ($namespace === $this->resourcesNamespace) {
            $namespace .= '\\RelationManagers';
            $directory = rtrim($directory, '/') . '/RelationManagers';
        }

        $this->fqn = "{$namespace}\\{$className}";
        $this->path = rtrim($directory, '/') . "/{$className}.php";
    }

    protected function createRelationManager(): void
    {
        if (! $this->option('force') && $this->checkForCollision($this->path)) {
            throw new FailureCommandOutput;
        }

        $recordTitleAttributeCode = '';

        if (filled($this->recordTitleAttribute)) {
            $recordTitleAttributeCode = "\n\n    protected static ?string \$recordTitleAttribute = " . var_export($this->recordTitleAttribute, true) . ';';
        }

        $this->copyStubToApp('AutoRelationManager', $this->path, [
            'namespace' => (string) str($this->fqn)->beforeLast('\\'),
            'managerClass' => class_basename($this->fqn),
            'relationship' => $this->relationship,
            'relationshipCode' => var_export($this->relationship, true),
            'recordTitleAttributeCode' => $recordTitleAttributeCode,
        ]);
    }

    protected function getDefaultStubPath(): string
    {
        return dirname(__DIR__, 2) . '/stubs';
    }
}
