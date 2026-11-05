<?php

use Illuminate\Filesystem\Filesystem;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoRelationManagerCommand;
use Miguilim\FilamentAutoPanel\Commands\MakeAutoResourceCommand;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\CommandGenerationRelationManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\CommandGenerationResource;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require_once __DIR__ . '/../Fixtures/CommandGenerationFixtures.php';

beforeEach(function (): void {
    foreach ([CommandGenerationResource::class, CommandGenerationRelationManager::class] as $class) {
        $this->app->resolving($class, function ($command): void {
            $command->setLaravel($this->app);
        });
    }
});

it('registers the documented command names without taking native Filament aliases', function (): void {
    foreach ([
        MakeAutoResourceCommand::class => 'make:filament-auto-resource',
        MakeAutoRelationManagerCommand::class => 'make:filament-auto-relation-manager',
    ] as $class => $name) {
        $command = $this->app->make($class);

        expect($command->getName())->toBe($name)
            ->and($command->getAliases())->toBe([]);
    }

    $commands = Illuminate\Support\Facades\Artisan::all();

    expect($commands['make:filament-auto-resource'])->toBeInstanceOf(MakeAutoResourceCommand::class)
        ->and($commands['make:filament-auto-relation-manager'])->toBeInstanceOf(MakeAutoRelationManagerCommand::class)
        ->and($commands['filament:resource'])->toBeInstanceOf(Filament\Commands\MakeResourceCommand::class)
        ->and($commands['filament:resource'])->not->toBeInstanceOf(MakeAutoResourceCommand::class);
});

it('generates single resource classes with matching paths and model basenames', function (string $model, string $path, string $namespace): void {
    $command = $this->app->make(CommandGenerationResource::class);
    $status = $command->run(new ArrayInput(['model' => $model]), new BufferedOutput);
    $source = $command->writtenFiles[$path];

    expect($status)->toBe(0)
        ->and($command->writtenFiles)->toHaveCount(1)
        ->and($source)->toContain("namespace {$namespace};")
        ->toContain('class ProductResource extends AutoResource')
        ->toContain('protected static ?string $model = Product::class;')
        ->not->toContain('{{');

    token_get_all($source, TOKEN_PARSE);
})->with([
    ['Product', '/auto-panel-command-fixture/Resources/ProductResource.php', 'App\\Filament\\Resources'],
    ['Admin/Product', '/auto-panel-command-fixture/Resources/Admin/ProductResource.php', 'App\\Filament\\Resources\\Admin'],
]);

it('serializes resource titles as PHP literals with the inherited nullable property type', function (): void {
    $title = "owner's\\title\n\$token";
    $command = $this->app->make(CommandGenerationResource::class);
    $status = $command->run(new ArrayInput(['model' => 'Product', '--record-title-attribute' => $title]), new BufferedOutput);
    $source = array_values($command->writtenFiles)[0];

    expect($status)->toBe(0)
        ->and($source)->toContain('protected static ?string $recordTitleAttribute = ' . var_export($title, true) . ';');

    token_get_all($source, TOKEN_PARSE);
});

it('removes only the Resource suffix from relation manager names', function (string $resource, string $expected): void {
    $command = $this->app->make(CommandGenerationRelationManager::class);
    $status = $command->run(new ArrayInput(['resource' => $resource, 'relationship' => 'orderItems']), new BufferedOutput);
    $source = $command->writtenFiles["/auto-panel-command-fixture/RelationManagers/{$expected}.php"];

    expect($status)->toBe(0)
        ->and($source)->toContain('namespace App\\Filament\\RelationManagers;')
        ->toContain("class {$expected} extends AutoRelationManager")
        ->toContain("protected static string \$relationship = 'orderItems';")
        ->not->toContain('{{');

    token_get_all($source, TOKEN_PARSE);
})->with([
    ['App\\Filament\\Resources\\CourseResource', 'CourseOrderItemsRelationManager'],
    ['App\\Filament\\Resources\\UserResource', 'UserOrderItemsRelationManager'],
    ['App\\Filament\\Resources\\Admin\\ProductResource', 'ProductOrderItemsRelationManager'],
]);

it('honors relation manager title arguments and options with safe serialization', function (bool $useOption): void {
    $title = "owner's\\title\n\$token";
    $arguments = ['resource' => 'App\\Filament\\Resources\\CourseResource', 'relationship' => 'products', 'recordTitleAttribute' => $useOption ? 'ignored' : $title];

    if ($useOption) {
        $arguments['--record-title-attribute'] = $title;
    }

    $command = $this->app->make(CommandGenerationRelationManager::class);
    $status = $command->run(new ArrayInput($arguments), new BufferedOutput);
    $source = array_values($command->writtenFiles)[0];

    expect($status)->toBe(0)
        ->and($source)->toContain('protected static ?string $recordTitleAttribute = ' . var_export($title, true) . ';');

    token_get_all($source, TOKEN_PARSE);
})->with([false, true]);

it('does not write on collision and honors force', function (string $class, array $arguments): void {
    $command = $this->app->make($class);
    $command->hasCollision = true;

    expect($command->run(new ArrayInput($arguments), new BufferedOutput))->toBe(1)
        ->and($command->writtenFiles)->toBe([])
        ->and($command->collisionChecks)->toBe(1);

    $command = $this->app->make($class);
    $command->hasCollision = true;

    expect($command->run(new ArrayInput([...$arguments, '--force' => true]), new BufferedOutput))->toBe(0)
        ->and($command->writtenFiles)->toHaveCount(1)
        ->and($command->collisionChecks)->toBe(0);
})->with([
    [CommandGenerationResource::class, ['model' => 'Product']],
    [CommandGenerationRelationManager::class, ['resource' => 'App\\Filament\\Resources\\CourseResource', 'relationship' => 'products']],
]);

it('resolves package stubs independently of a command subclass location', function (): void {
    $expected = dirname(__DIR__, 2) . '/stubs';

    expect($this->app->make(CommandGenerationResource::class)->defaultStubPath())->toBe($expected)
        ->and($this->app->make(CommandGenerationRelationManager::class)->defaultStubPath())->toBe($expected);
});

it('keeps application stub overrides under stubs/filament', function (string $class, array $arguments, string $stub): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $overridePath = base_path("stubs/filament/{$stub}.stub");
    $filesystem->shouldReceive('exists')->with($overridePath)->once()->andReturnTrue();
    $filesystem->shouldReceive('get')->with($overridePath)->once()->andReturn('custom {{ namespace }} {{ recordTitleAttributeCode }}');
    $this->app->instance(Filesystem::class, $filesystem);
    $command = $this->app->make($class);

    expect($command->run(new ArrayInput($arguments), new BufferedOutput))->toBe(0)
        ->and(array_values($command->writtenFiles)[0])->toStartWith('custom App\\Filament\\');
})->with([
    [CommandGenerationResource::class, ['model' => 'Product'], 'AutoResource'],
    [CommandGenerationRelationManager::class, ['resource' => 'App\\Filament\\Resources\\CourseResource', 'relationship' => 'products'], 'AutoRelationManager'],
]);
