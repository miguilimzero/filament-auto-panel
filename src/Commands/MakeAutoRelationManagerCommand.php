<?php

namespace Miguilim\FilamentAutoPanel\Commands;

use function Laravel\Prompts\text;

use ReflectionClass;
use Illuminate\Support\Str;

use Filament\Commands\MakeRelationManagerCommand;

class MakeAutoRelationManagerCommand extends MakeRelationManagerCommand
{
     /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:filament-auto-relation-manager {resource?} {relationship?} {recordTitleAttribute?} {--panel=} {--F|force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Filament auto relation manager class for a resource';

    public function handle(): int
    {
        $resource = (string) str(
            $this->argument('resource') ?? text(
                label: 'What is the resource you would like to create this in?',
                placeholder: 'DepartmentResource',
                required: true,
            ),
        )
            ->studly()
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');

        $this->input->setArgument('resource', $resource);

        return parent::handle();
    }

    public function option($key = null) { 
        if ($key === 'soft-deletes' || $key === 'attach' || $key === 'associate' || $key === 'view') {
            return false;
        }

        return parent::option($key);
    }

    protected function copyStubToApp(string $stub, string $targetPath, array $replacements = []): void 
    { 
        if($stub !== 'RelationManager') {
            return;
        }

        $replacements['relatedResource'] = $this->argument('resource');

        parent::copyStubToApp('AutoRelationManager', $targetPath, $replacements);
    }

    protected function getDefaultStubPath(): string
    {
        $reflectionClass = new ReflectionClass($this);

        return (string) Str::of($reflectionClass->getFileName())
            ->beforeLast('Commands')
            ->append('../stubs');
    }
}