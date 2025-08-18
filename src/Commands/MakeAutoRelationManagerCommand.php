<?php

namespace Miguilim\FilamentAutoPanel\Commands;

use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Commands\MakeRelationManagerCommand;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;

class MakeAutoRelationManagerCommand extends MakeRelationManagerCommand
{
    use CanManipulateFiles;

    protected $description = 'Create a new Filament auto relation manager class';

    protected $name = 'make:filament-auto-relation-manager';

    public function handle(): int
    {
        try {
            $this->configurePanel(question: 'Which panel would you like to create this relation manager in?');
            $this->configureResource();
            $this->configureRelationship();
            // $this->configureRelatedResource();

            // if (blank($this->relatedResourceFqn)) {
            //     $this->configureFormSchemaFqn();

            //     if (blank($this->formSchemaFqn)) {
            //         $this->configureIsGeneratedIfNotAlready();

            //         $this->isGenerated
            //             ? $this->configureRelatedModelFqnIfNotAlready()
            //             : $this->configureRecordTitleAttributeIfNotAlready();
            //     }

            //     $this->configureHasViewOperation();

            //     if ($this->hasViewOperation) {
            //         $this->configureInfolistSchemaFqn();

            //         if (blank($this->infolistSchemaFqn)) {
            //             $this->configureRecordTitleAttributeIfNotAlready();
            //         }
            //     }

            //     if ($this->hasFileGenerationFlag(FileGenerationFlag::EMBEDDED_PANEL_RESOURCE_TABLES)) {
            //         $this->configureIsGeneratedIfNotAlready();

            //         $this->isGenerated
            //             ? $this->configureRelatedModelFqnIfNotAlready()
            //             : $this->configureRecordTitleAttributeIfNotAlready();
            //     } else {
            //         $this->configureTableFqn();
            //     }

            //     if (blank($this->tableFqn)) {
            //         $this->configureRecordTitleAttributeIfNotAlready();

            //         $this->configureIsGeneratedIfNotAlready(
            //             question: 'Should the table columns be generated from the current database columns?',
            //         );

            //         if ($this->isGenerated) {
            //             $this->configureRelatedModelFqnIfNotAlready();
            //         }

            //         $this->configureIsSoftDeletable();

            //         $this->configureRelationshipType();
            //     }
            // }

            $this->configureLocation();

            $this->createRelationManager();
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        $this->components->info("Filament relation manager created successfully.");

        $this->components->info("Make sure to register the relation in [{$this->resourceFqn}::getRelations()].");

        return static::SUCCESS;
    }

    protected function createRelationManager(): void
    {
        $explodedFqd = explode('\\', $this->fqn);
        $realFqnEnd = end($explodedFqd);

        $explodedResourceFqd = explode('\\', $this->resourceFqn);
        $resourceRealFqnEnd = end($explodedResourceFqd);

        $className = rtrim($resourceRealFqnEnd, 'Resource') . $realFqnEnd;
        $relationManagerDirectory = str_replace('/Resources', '/RelationManagers', $this->resourcesDirectory);

        $path = (string) str("{$relationManagerDirectory}\\{$className}.php")
            ->replace('\\', '/')
            ->replace('//', '/');

        if (! $this->option('force') && $this->checkForCollision($path)) {
            throw new FailureCommandOutput;
        }

        $recordTitleAttributeCode = '';
        if ($this->recordTitleAttribute) {
            $recordTitleAttributeCode = "\n\n    protected static string \$recordTitleAttribute = '{$this->recordTitleAttribute}';";
        }

        $this->copyStubToApp('AutoRelationManager', $path, [
            'namespace' => str_replace('\\Resources', '\\RelationManagers', $this->resourcesNamespace),
            'managerClass' => $className,
            'relationship' => $this->relationship,
            'recordTitleAttributeCode' => $recordTitleAttributeCode,
        ]);
    }
}
