<?php

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\JsContent;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ProductResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';

beforeEach(function (): void {
    if (DB::getDriverName() === 'sqlite') {
        // Laravel omits varchar lengths on SQLite; declare them explicitly for schema introspection.
        foreach (['short_name' => 20, 'email' => 120, 'phone' => 30] as $name => $length) {
            DB::statement('alter table products add column ' . $name . ' varchar(' . $length . ')');
        }

        return;
    }

    Schema::table('products', function (Blueprint $table): void {
        $table->string('short_name', 20)->nullable();
        $table->string('email', 120)->nullable();
        $table->string('phone', 30)->nullable();
    });
});

function characterCounterForm(string $surface = 'resource', string $statePath = 'userFieldData'): FilamentSchema
{
    $schema = FilamentSchema::make(new UserFieldListRecords())->model(Product::class)->statePath($statePath);

    if ($surface === 'resource') {
        return ProductResource::form($schema);
    }

    $class = $surface === 'standalone manager'
        ? RelationManagerMounter::makeStandalone('products', visibleColumns: [], associateAttachActions: false)
        : RelationManagerMounter::makeFromResource(ProductResource::class, 'products', associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = Owner::create(['name' => 'Owner']);
    $manager->pageClass = ListProducts::class;

    return $manager->form($schema);
}

function characterCounterField(FilamentSchema $form, string $name = 'short_name'): TextInput
{
    return collect($form->getFlatComponents(withHidden: true))->first(fn ($field) => $field instanceof TextInput && $field->getName() === $name);
}

function characterCounterText(TextInput $field): Text
{
    return $field->getChildSchema(TextInput::BELOW_CONTENT_SCHEMA_KEY)->getComponents()[0];
}

it('renders a muted client-side character counter below bounded strings in resources and relation managers', function (string $surface): void {
    $form = characterCounterForm($surface);
    $form->fill(['short_name' => 'Fifteen letters']);
    $field = characterCounterField($form);
    $counter = characterCounterText($field);
    $html = str_replace('\\/', '/', $field->toHtml());

    expect($field->getMaxLength())->toBe(20)
        ->and($counter->getContent())->toBeInstanceOf(JsContent::class)
        ->and($counter->getColor())->toBe('gray')
        ->and($counter->getExtraAttributes()['style'])->toContain('width:100%', 'text-align:right')
        ->and($html)->toContain('maxlength="20"', 'x-text=', ' / 20', 'fi-sc-text')
        ->and(strpos($html, ' / 20'))->toBeGreaterThan(strpos($html, '<input'))
        ->and($html)->not->toContain('wire:model.live')
        ->and($field->getState())->toBe('Fifteen letters');
})->with(['resource', 'standalone manager', 'resource-backed manager']);

it('uses each fields detected maximum including email and telephone inputs', function (string $name, int $max): void {
    $field = characterCounterField(characterCounterForm(), $name);
    expect($field->getMaxLength())->toBe($max)
        ->and(str_replace('\\/', '/', characterCounterText($field)->getContent()->toHtml()))->toContain(' / ' . $max);
})->with(['short name' => ['short_name', 20], 'email' => ['email', 120], 'phone' => ['phone', 30]]);

it('does not add counters to numeric inputs primary keys textareas or custom field overrides', function (): void {
    $fields = FormGenerator::makeForModel(new Product(), overwriteColumns: ['short_name' => TextInput::make('short_name')->maxLength(10)], relationManagerView: true);
    FilamentSchema::make(new UserFieldListRecords())->model(Product::class)->statePath('userFieldData')->components($fields)->getFlatComponents(withHidden: true);

    foreach (['price', 'quantity', 'id', 'description', 'short_name'] as $name) {
        expect($fields[$name]->getChildSchema(TextInput::BELOW_CONTENT_SCHEMA_KEY))->toBeNull();
    }
});

it('does not invent a counter limit for unbounded strings', function (): void {
    $model = new Product();
    $model->mergeCasts(['description' => 'string']);
    $fields = FormGenerator::makeForModel($model, relationManagerView: true);
    FilamentSchema::make(new UserFieldListRecords())->model($model)->components($fields)->getFlatComponents(withHidden: true);

    // MySQL text columns have a byte bound, unlike SQLite's unbounded text.
    if ($fields['description']->getMaxLength() === null) {
        expect($fields['description']->getChildSchema(TextInput::BELOW_CONTENT_SCHEMA_KEY))->toBeNull();
    } else {
        expect(str_replace('\\/', '/', characterCounterText($fields['description'])->getContent()->toHtml()))->toContain(' / ' . $fields['description']->getMaxLength());
    }
});

it('counts Unicode characters and reacts to edits resets and nested form state without server requests', function (): void {
    $node = (new ExecutableFinder())->find('node');
    if ($node === null) {
        $this->markTestSkipped('Node.js is required to evaluate the client-side counter expression.');
    }

    $field = characterCounterField(characterCounterForm(statePath: 'userFieldData.rows.item'));
    $document = new DOMDocument();
    $document->loadHTML(characterCounterText($field)->getContent()->toHtml(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $expression = $document->getElementsByTagName('span')->item(0)->getAttribute('x-text');
    $script = <<<'JS'
        const render = new Function('$get', 'return (' + process.argv[1] + ')()');
        let state;
        const get = (path, absolute) => {
            if (path !== 'userFieldData.rows.item.short_name' || absolute !== true) throw new Error('Wrong field path');
            return state;
        };
        const results = [null, '', 'Fifteen letters', 'é😀漢', '0', 'x'.repeat(20), 'x'.repeat(21), '<script>alert(1)</script>', null].map(value => {
            state = value;
            return render(get);
        });
        console.log(JSON.stringify(results));
        JS;
    $process = new Process([$node, '-e', $script, $expression]);
    $process->setTimeout(10)->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe([
        '0 / 20', '0 / 20', '15 / 20', '3 / 20', '1 / 20', '20 / 20', '21 / 20', '25 / 20', '0 / 20',
    ]);
});
