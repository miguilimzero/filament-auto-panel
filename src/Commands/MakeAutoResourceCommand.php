<?php

namespace Miguilim\FilamentAutoResource\Commands;

use ReflectionClass;
use Illuminate\Support\Str;

use Filament\Commands\MakeResourceCommand;

class MakeAutoResourceCommand extends MakeResourceCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:filament-auto-resource {name?} {--soft-deletes} {--view} {--S|simple} {--F|force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Filament auto resource class';

    public function option($key = null) { 
        if ($key === 'generate') {
            return true;
        }

        return parent::option($key);
    }

    protected function copyStubToApp(string $stub, string $targetPath, array $replacements = []): void 
    { 
        if($stub !== 'Resource') {
            return;
        }

        parent::copyStubToApp('AutoResource', $targetPath, $replacements);
    }

    protected function getResourceTableColumns(string $model): string 
    {
        return '//';
    }

    protected function getResourceFormSchema(string $model): string 
    {
        return '//';
    }

    protected function getDefaultStubPath(): string
    {
        $reflectionClass = new ReflectionClass($this);

        return (string) Str::of($reflectionClass->getFileName())
            ->beforeLast('Commands')
            ->append('../stubs');
            // ->append('../vendor/filament/filament/stubs');
    }
}