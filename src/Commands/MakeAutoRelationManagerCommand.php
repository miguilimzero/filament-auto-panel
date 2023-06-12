<?php

namespace Miguilim\FilamentAutoResource\Commands;

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
    protected $signature = 'make:filament-auto-relation-manager {resource?} {relationship?} {recordTitleAttribute?} {--F|force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Filament auto relation manager class for a resource';

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