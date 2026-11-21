<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretAccount;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretAccountGuardedResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretAccountResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretCustomFormGuardedResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretCustomFormResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretEmailExcludedResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretOwnerResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\SecretSearchableTitleResource;

require_once __DIR__ . '/../Fixtures/SecretColumnFixtures.php';

beforeEach(function (): void {
    Schema::create('secret_accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('owner_id')->nullable();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password');
        $table->text('api_token')->nullable();
        $table->text('settings')->nullable();
        $table->string('margin')->nullable();
        $table->string('internal_note')->nullable();
        $table->string('legacy_key')->nullable();
        $table->string('remember_token')->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
    });
});

afterEach(fn () => Schema::dropIfExists('secret_accounts'));

/** @param array<class-string> $resources */
function secretPanel(array $resources): void
{
    $panel = Panel::make()->id('secrets')->path('secrets')->resources($resources);
    Filament::setCurrentPanel($panel);

    foreach ($resources as $resource) {
        Route::name('filament.' . $panel->getId() . '.')
            ->prefix($panel->getPath())
            ->group(fn () => $resource::registerRoutes($panel));
    }

    Route::getRoutes()->refreshNameLookups();
}

function secretAccount(array $attributes = []): SecretAccount
{
    return SecretAccount::create([
        'name' => 'Visible name',
        'email' => 'visible@example.test',
        'password' => 'original-password',
        'api_token' => 'plain-api-token',
        'settings' => ['mode' => 'plain-setting'],
        'margin' => 'margin-value-7',
        'internal_note' => 'internal-note-value',
        'legacy_key' => 'legacy-key-value',
        'remember_token' => 'remember-token-value',
        ...$attributes,
    ]);
}

function secretSnapshot(Testable $component): string
{
    return json_encode($component->snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function secretPayload(Testable $component): string
{
    return $component->html() . secretSnapshot($component);
}

/** @return array<string> */
function secretFieldNames(array $components): array
{
    $schema = FilamentSchema::make(new ListProducts())->components($components);

    return array_values(array_map(
        fn ($component): string => $component->getName(),
        array_filter($schema->getFlatComponents(withHidden: true), fn ($component): bool => method_exists($component, 'getName')),
    ));
}

/** @return array<string> */
function secretFilterNames(Table $table): array
{
    $names = [];

    foreach ($table->getFilters() as $filter) {
        if (method_exists($filter, 'getConstraints')) {
            foreach ($filter->getConstraints() as $constraint) {
                $names[] = $constraint->getName();
            }
        }
    }

    return $names;
}

function secretManager(bool $resourceBacked, SecretOwner $owner): Testable
{
    $class = $resourceBacked
        ? RelationManagerMounter::makeFromResource(SecretAccountResource::class, 'secretAccounts')
        : RelationManagerMounter::makeStandalone('secretAccounts', [], excludedColumns: ['legacy_key']);

    return Livewire::test($class, [
        'ownerRecord' => $owner,
        'pageClass' => SecretOwnerResource::getPages()['view']->getPage(),
    ]);
}

dataset('secret relation managers', ['standalone' => [false], 'resource-backed' => [true]]);

// --- Exclude list ---------------------------------------------------------------------------

it('never generates an excluded column for a resource', function (): void {
    secretPanel([SecretAccountResource::class]);
    $table = SecretAccountResource::table(Table::make(new ListProducts()));
    $host = new ListProducts();

    expect(array_keys($table->getColumns()))->toContain('name')->not->toContain('legacy_key')
        ->and(secretFilterNames($table))->toContain('name')->not->toContain('legacy_key')
        ->and(secretFieldNames(SecretAccountResource::form(FilamentSchema::make($host)->operation('edit'))->getComponents()))
        ->toContain('name')->not->toContain('legacy_key')
        ->and(secretFieldNames(SecretAccountResource::infolist(FilamentSchema::make($host))->getComponents()))
        ->toContain('name')->not->toContain('legacy_key');
});

it('never generates an excluded column for relation managers', function (bool $resourceBacked): void {
    secretPanel([SecretOwnerResource::class, SecretAccountResource::class]);
    $owner = SecretOwner::create(['name' => 'Owner']);
    secretAccount(['owner_id' => $owner->getKey()]);
    $manager = secretManager($resourceBacked, $owner)->instance();
    $table = $manager->getTable();
    $schema = fn (): FilamentSchema => FilamentSchema::make($manager)->model(SecretAccount::class);

    expect(array_keys($table->getColumns()))->toContain('name')->not->toContain('legacy_key')
        ->and(secretFilterNames($table))->toContain('name')->not->toContain('legacy_key')
        ->and(secretFieldNames($manager->form($schema())->getComponents()))->toContain('name')->not->toContain('legacy_key')
        ->and(secretFieldNames($manager->infolist($schema())->getComponents()))->toContain('name')->not->toContain('legacy_key');
})->with('secret relation managers');

it('keeps excluded columns out of table, global and relation-select searches', function (): void {
    secretPanel([SecretSearchableTitleResource::class]);

    expect(SecretSearchableTitleResource::getGloballySearchableAttributes())->toBe([])
        ->and(fn () => TableGenerator::make(SecretAccount::class, searchableColumns: ['legacy_key' => 'global'], excludedColumns: ['legacy_key']))
        ->toThrow(InvalidArgumentException::class, 'Searchable column(s) [legacy_key] do not exist');

    secretPanel([SecretEmailExcludedResource::class]);
    $related = new SecretAccount();

    expect(RelationshipGuesser::guessModelTitleColumnName($related))->toBe('id')
        ->and(RelationshipGuesser::guessModelSearchColumns($related, 'id'))->toBe(['id']);

    secretPanel([SecretAccountResource::class]);

    expect(RelationshipGuesser::guessModelTitleColumnName($related))->toBe('name')
        ->and(RelationshipGuesser::guessModelSearchColumns($related, 'name'))->toBe(['name', 'id', 'email']);
});

it('throws when an excluded column does not exist', function (string $generator): void {
    expect(fn () => $generator::make(SecretAccount::class, excludedColumns: ['missing_column']))
        ->toThrow(InvalidArgumentException::class, 'Excluded column(s) [missing_column] do not exist');
})->with([[TableGenerator::class], [FormGenerator::class], [InfolistGenerator::class]]);

it('keeps an excluded value out of the resource edit snapshot', function (string $resource): void {
    secretPanel([$resource]);
    $record = secretAccount();

    $component = Livewire::test($resource::getPages()['view']->getPage(), ['record' => $record->getKey()])
        ->mountAction('edit')
        ->assertActionMounted('edit');

    expect($component->instance()->mountedActions[0]['data'])->toHaveKey('name', 'Visible name')->not->toHaveKey('legacy_key')
        ->and(secretPayload($component))->toContain('Visible name')->not->toContain('legacy-key-value');
})->with([[SecretAccountResource::class], [SecretAccountGuardedResource::class]]);

it('keeps an excluded value out of relation manager edit and view snapshots', function (bool $resourceBacked): void {
    secretPanel([SecretOwnerResource::class, SecretAccountResource::class]);
    $owner = SecretOwner::create(['name' => 'Owner']);
    $record = secretAccount(['owner_id' => $owner->getKey()]);

    expect(secretPayload(secretManager($resourceBacked, $owner)))->toContain('Visible name')->not->toContain('legacy-key-value');

    // Resource-backed managers link to the resource pages instead of opening modals.
    foreach ($resourceBacked ? [] : ['edit', 'view'] as $action) {
        $component = secretManager($resourceBacked, $owner)
            ->mountAction(TestAction::make($action)->table($record))
            ->assertActionMounted(TestAction::make($action)->table($record));

        expect(secretPayload($component))->toContain('Visible name')->not->toContain('legacy-key-value');
    }
})->with('secret relation managers');

// --- Fill only what the form shows ---------------------------------------------------------

it('fills only attributes that have a form field', function (string $resource, bool $intrusive): void {
    secretPanel([$resource]);
    $record = secretAccount();

    $component = Livewire::test($resource::getPages()['view']->getPage(), ['record' => $record->getKey()])
        ->mountAction('edit')
        ->assertActionMounted('edit');
    $data = $component->instance()->mountedActions[0]['data'];
    // The infolist legitimately renders these values, so only the snapshot is inspected.
    $snapshot = secretSnapshot($component);

    // `internal_note` is $hidden and `email` is not, but neither has a field in this form.
    expect(array_keys($data))->toEqualCanonicalizing(['name', 'margin'])
        ->and($data['name'])->toBe('Visible name')
        ->and($snapshot)->toContain('Visible name');

    foreach (['internal-note-value', 'visible@example.test', 'legacy-key-value', 'plain-api-token', $record->password] as $value) {
        expect($snapshot)->not->toContain($value);
    }

    // `margin` is $hidden but has a field: only intrusive mode may fill it.
    if ($intrusive) {
        expect($data['margin'])->toBe('margin-value-7')->and($snapshot)->toContain('margin-value-7');
    } else {
        expect($data['margin'])->toBeNull()->and($snapshot)->not->toContain('margin-value-7');
    }
})->with([
    'intrusive' => [SecretCustomFormResource::class, true],
    'non-intrusive' => [SecretCustomFormGuardedResource::class, false],
]);

// --- Hashed casts ---------------------------------------------------------------------------

it('never renders a hashed column', function (): void {
    secretPanel([SecretAccountResource::class]);
    $record = secretAccount();
    $host = new ListProducts();

    expect(array_keys(TableGenerator::make(SecretAccount::class)))->not->toContain('password')
        ->and(secretFieldNames(InfolistGenerator::make(SecretAccount::class)))->not->toContain('password')
        ->and(secretFieldNames(SecretAccountResource::form(FilamentSchema::make($host)->operation('edit'))->getComponents()))->toContain('password');

    Livewire::test(SecretAccountResource::getPages()['index']->getPage())->assertDontSee($record->password);

    $view = Livewire::test(SecretAccountResource::getPages()['view']->getPage(), ['record' => $record->getKey()])
        ->assertDontSee($record->password)
        ->mountAction('edit');

    expect($view->instance()->mountedActions[0]['data']['password'])->toBeNull()
        ->and(secretPayload($view))->not->toContain($record->password);
});

it('keeps the stored hash when the password is left empty and hashes a new one', function (): void {
    secretPanel([SecretAccountResource::class]);
    $record = secretAccount();
    $hash = $record->password;
    $page = SecretAccountResource::getPages()['view']->getPage();

    Livewire::test($page, ['record' => $record->getKey()])
        ->callAction('edit', data: ['name' => 'Renamed'])
        ->assertHasNoFormErrors();

    expect($record->refresh()->name)->toBe('Renamed')
        ->and($record->password)->toBe($hash);

    Livewire::test($page, ['record' => $record->getKey()])
        ->callAction('edit', data: ['password' => 'brand-new-password'])
        ->assertHasNoFormErrors();

    expect($record->refresh()->password)->not->toBe($hash)->not->toBe('brand-new-password')
        ->and(Hash::check('brand-new-password', $record->password))->toBeTrue();
});

it('requires a hashed NOT NULL column on create only', function (): void {
    secretPanel([SecretAccountResource::class]);
    $page = SecretAccountResource::getPages()['index']->getPage();

    Livewire::test($page)
        ->callAction('create', data: ['name' => 'Created'])
        ->assertHasFormErrors(['password' => 'required']);

    expect(SecretAccount::count())->toBe(0);

    Livewire::test($page)
        ->callAction('create', data: ['name' => 'Created', 'password' => 'first-password'])
        ->assertHasNoFormErrors();

    expect(Hash::check('first-password', SecretAccount::sole()->password))->toBeTrue();
});

// --- Encrypted casts ------------------------------------------------------------------------

it('hides encrypted columns by default and never searches or sorts them', function (): void {
    $columns = TableGenerator::make(SecretAccount::class);

    foreach (['api_token', 'settings'] as $name) {
        expect($columns[$name]->isToggleable())->toBeTrue()
            ->and($columns[$name]->isToggledHiddenByDefault())->toBeTrue()
            ->and($columns[$name]->isSearchable())->toBeFalse()
            ->and($columns[$name]->isSortable())->toBeFalse();
    }

    expect($columns['name']->isToggledHiddenByDefault())->toBeFalse()
        ->and(TableGenerator::make(SecretAccount::class, visibleColumns: ['api_token'])['api_token']->isToggledHiddenByDefault())->toBeFalse()
        ->and(fn () => TableGenerator::make(SecretAccount::class, searchableColumns: ['api_token' => 'global']))
        ->toThrow(InvalidArgumentException::class, 'Encrypted column(s) [api_token]');
});

it('shows the decrypted value in the infolist and keeps ciphertext in the database after an edit', function (): void {
    secretPanel([SecretAccountResource::class]);
    $record = secretAccount();
    $page = SecretAccountResource::getPages()['view']->getPage();
    $raw = fn (string $column): string => DB::table('secret_accounts')->where('id', $record->getKey())->value($column);

    expect($raw('api_token'))->not->toContain('plain-api-token');

    Livewire::test($page, ['record' => $record->getKey()])
        ->assertSee('plain-api-token')
        ->assertSee('plain-setting')
        ->callAction('edit', data: ['api_token' => 'changed-api-token', 'settings' => ['mode' => 'changed-setting']])
        ->assertHasNoFormErrors();

    expect($raw('api_token'))->not->toContain('changed-api-token')
        ->and($raw('settings'))->not->toContain('changed-setting')
        ->and($record->refresh()->api_token)->toBe('changed-api-token')
        ->and($record->settings)->toBe(['mode' => 'changed-setting']);

    // An untouched round-trip must not store the decrypted value either.
    Livewire::test($page, ['record' => $record->getKey()])->callAction('edit')->assertHasNoFormErrors();

    expect($raw('api_token'))->not->toContain('changed-api-token')
        ->and($record->refresh()->api_token)->toBe('changed-api-token')
        ->and($record->settings)->toBe(['mode' => 'changed-setting']);
});
