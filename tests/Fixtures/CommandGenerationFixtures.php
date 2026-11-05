<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Panel;
use Filament\Support\Commands\FileGenerators\Contracts\FileGenerator;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoRelationManagerCommand;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoResourceCommand;

trait CapturesAutoPanelCommandOutput
{
    public array $writtenFiles = [];

    public bool $hasCollision = false;

    public int $collisionChecks = 0;

    protected function writeFile(string $path, string | FileGenerator $contents): void
    {
        $this->writtenFiles[$path] = $contents;
    }

    protected function checkForCollision(string | array $paths): bool
    {
        $this->collisionChecks++;

        return $this->hasCollision;
    }

    protected function configurePanel(string $question, ?string $initialQuestion = null): void
    {
        $this->panel = Panel::make()->id('command-generation');
    }

    protected function configureResourcesLocation(string $question): void
    {
        $this->resourcesNamespace = 'App\\Filament\\Resources';
        $this->resourcesDirectory = '/auto-panel-command-fixture/Resources/';
    }

    public function defaultStubPath(): string
    {
        return $this->getDefaultStubPath();
    }
}

class CommandGenerationResource extends MakeAutoResourceCommand
{
    use CapturesAutoPanelCommandOutput;

    protected function configureModel(): void
    {
        $this->modelFqnEnd = str_replace('/', '\\', (string) $this->argument('model'));
        $this->modelFqn = 'App\\Models\\' . $this->modelFqnEnd;
    }

    protected function configureRecordTitleAttribute(): void
    {
        $this->recordTitleAttribute = $this->option('record-title-attribute');
    }
}

class CommandGenerationRelationManager extends MakeAutoRelationManagerCommand
{
    use CapturesAutoPanelCommandOutput;

    protected function configureResource(): void
    {
        $this->configureResourcesLocation('Unused');
        $this->resourceFqn = $this->argument('resource');
    }

    protected function configureRelationship(): void
    {
        $this->relationship = $this->argument('relationship');
    }
}
