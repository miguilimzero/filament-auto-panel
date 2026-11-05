<?php

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Resources\ResourceConfiguration;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Filament\RelationManagers\UserSessionsRelationManager;
use Miguilim\FilamentAutoPanel\Models\DatabaseSession;
use Miguilim\FilamentAutoPanel\Support\UserSessions;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsAuditManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsConfiguredResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsCustomRelationsResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsIdentifierResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsIdentifierUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsNativeResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsNativeView;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsOtherUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsPlainModel;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsPlainResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsPolicy;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsReadOnlyResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsSecondAuditManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsTeam;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsTenantResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsThirdAuditManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserSessionsUser;

require_once __DIR__ . '/../Fixtures/UserSessionsFixtures.php';

beforeEach(function (): void {
    $this->travelTo(now()->startOfSecond());
    UserSessionsPolicy::$view = true;
    UserSessionsPolicy::$edit = true;
    UserSessionsResource::$restrictQuery = false;
    UserSessionsCustomRelationsResource::$relations = [];

    config([
        'auth.defaults' => ['guard' => 'web', 'passwords' => 'users'],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => UserSessionsUser::class],
        ],
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'panel' => ['driver' => 'session', 'provider' => 'users'],
        ],
        'session.driver' => 'database',
        'session.connection' => 'testing',
        'session.table' => 'user_sessions_sessions',
        'session.lifetime' => 30,
    ]);
    Auth::forgetGuards();
    UserSessionsFixtures::create();
    Gate::policy(UserSessionsUser::class, UserSessionsPolicy::class);
    Gate::policy(UserSessionsIdentifierUser::class, UserSessionsPolicy::class);
    registerUserSessionsResource();
    $this->actingAs(UserSessionsFixtures::user(attributes: ['name' => 'Panel administrator']), 'panel');
});

afterEach(function (): void {
    config(['database.default' => 'testing']);
    if (method_exists(Filament::getFacadeRoot(), 'setCurrentResourceConfigurationKey')) {
        Filament::setCurrentResourceConfigurationKey(null);
    }
    Filament::setTenant(null, isQuiet: true);
    UserSessionsPolicy::$view = true;
    UserSessionsPolicy::$edit = true;
    UserSessionsResource::$restrictQuery = false;
    UserSessionsCustomRelationsResource::$relations = [];
    UserSessionsFixtures::drop();
    $this->travelBack();
});

function registerUserSessionsResource(string $resource = UserSessionsResource::class, string $guard = 'panel'): Panel
{
    $panel = Panel::make()->id('user-sessions')->path('user-sessions-admin')
        ->authGuard($guard)->resources([$resource]);
    Filament::setCurrentPanel($panel);
    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(fn () => $resource::registerRoutes($panel));
    Route::getRoutes()->refreshNameLookups();

    return $panel;
}

function registerConfiguredUserSessionsResource(?string $activeKey = 'visible'): Panel
{
    if (! class_exists(ResourceConfiguration::class)
        || ! method_exists(Filament::getFacadeRoot(), 'getResourceConfiguration')
        || ! method_exists(Filament::getFacadeRoot(), 'setCurrentResourceConfigurationKey')
        || ! method_exists(UserSessionsConfiguredResource::class, 'withConfiguration')) {
        test()->markTestSkipped('This Filament version does not support resource configurations.');
    }

    $configurations = [
        ...array_map(fn (string $key): ResourceConfiguration => UserSessionsConfiguredResource::make($key)->slug('configured-' . $key), [
            'visible', 'hidden', 'other-model',
        ]),
        ResourceConfiguration::make(UserSessionsResource::class, 'unrelated')->slug('configured-unrelated'),
    ];
    $panel = Panel::make()->id('user-sessions')->path('user-sessions-admin')
        ->authGuard('panel')->resources($configurations);
    Filament::setCurrentPanel($panel);
    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(function () use ($panel, $configurations): void {
            foreach ($configurations as $configuration) {
                $resource = $configuration->getResource();
                $resource::withConfiguration($configuration->getKey(), fn () => $resource::registerRoutes($panel, configuration: $configuration));
            }
        });
    Route::getRoutes()->refreshNameLookups();
    Filament::setCurrentResourceConfigurationKey($activeKey);

    return $panel;
}

function userSessionsPage(string $resource = UserSessionsResource::class): string
{
    return $resource::getPages()['view']->getPage();
}

function userSessionsManager(UserSessionsUser $owner, string $resource = UserSessionsResource::class): Testable
{
    return Livewire::test(UserSessionsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => userSessionsPage($resource),
    ])->assertSuccessful();
}

function userSessionsRecord(string $id): DatabaseSession
{
    return UserSessions::newModel()->newQuery()->findOrFail($id);
}

function userSessionsExists(string $id, ?string $connection = null, ?string $table = null): bool
{
    return DB::connection($connection ?? config('session.connection') ?? config('database.default'))
        ->table($table ?? config('session.table'))->where('id', $id)->exists();
}

function userSessionsClasses(array $relations): array
{
    $classes = [];
    foreach ($relations as $relation) {
        if ($relation instanceof RelationGroup) {
            array_push($classes, ...userSessionsClasses($relation->getManagers()));
        } else {
            $classes[] = $relation instanceof RelationManagerConfiguration ? $relation->relationManager : $relation;
        }
    }

    return $classes;
}

function assertUserSessionsPrivate(Testable $component, array $secrets): void
{
    // html(false) retains the initial wire:snapshot; subsequent snapshots/effects are checked too.
    $output = $component->html(false) . json_encode($component->snapshot, JSON_THROW_ON_ERROR)
        . json_encode($component->effects, JSON_THROW_ON_ERROR);
    foreach ($secrets as $secret) {
        expect($output)->not->toContain($secret);
    }
}

function userSessionsAttemptRevoke(Testable $component, string $key): void
{
    // Use the public Livewire endpoint, not callAction(), which asserts visibility before sending a request.
    $component->call('mountAction', 'revoke', [], ['table' => true, 'recordKey' => $key])
        ->call('callMountedAction');
}

it('automatically registers the native sessions manager through the default AutoResource relations method', function (): void {
    expect((new ReflectionMethod(UserSessionsResource::class, 'getRelations'))->getDeclaringClass()->getName())
        ->toBe(AutoResource::class)
        ->and((new ReflectionMethod(AutoResource::class, 'withAutomaticRelations'))->isPublic())->toBeTrue()
        ->and(UserSessionsResource::getRelations())->toBe([UserSessionsRelationManager::class])
        ->and(is_subclass_of(UserSessionsRelationManager::class, RelationManager::class))->toBeTrue()
        ->and(method_exists(UserSessionsUser::class, 'sessions'))->toBeFalse();
});

it('resolves the stable provider-registered Livewire alias without registering a test-only alias', function (): void {
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner);
    Livewire::test('filament-auto-panel.user-sessions', [
        'ownerRecord' => $owner,
        'pageClass' => userSessionsPage(),
    ])->assertSuccessful()->assertCanSeeTableRecords([userSessionsRecord($id)]);
});

it('preserves custom relation managers configurations groups and order while appending sessions', function (): void {
    $configured = UserSessionsSecondAuditManager::make(['marker' => 'preserved configuration']);
    $group = RelationGroup::make('Preserved group', [UserSessionsThirdAuditManager::class])->badge('7');
    $relations = [UserSessionsAuditManager::class, $configured, $group];
    $result = UserSessionsResource::withAutomaticRelations($relations);

    expect($result)->toBe([...$relations, UserSessionsRelationManager::class])
        ->and($result[1]->getProperties())->toBe(['marker' => 'preserved configuration'])
        ->and($result[2]->getLabel())->toBe('Preserved group')
        ->and($result[2]->getBadge())->toBe('7')
        ->and(UserSessionsResource::withAutomaticRelations($result))->toBe($result);
});

it('does not duplicate manually registered sessions classes configurations or grouped managers', function (string $registration): void {
    $sessions = match ($registration) {
        'class' => UserSessionsRelationManager::class,
        'configuration' => UserSessionsRelationManager::make(['isLazy' => false]),
        'group' => RelationGroup::make('Security', [UserSessionsRelationManager::make(['isLazy' => false])]),
    };
    $relations = [UserSessionsAuditManager::class, $sessions, UserSessionsSecondAuditManager::class];
    $result = UserSessionsResource::withAutomaticRelations($relations);

    expect($result)->toBe($relations)
        ->and(array_count_values(userSessionsClasses($result))[UserSessionsRelationManager::class])->toBe(1);
})->with(['class', 'configuration', 'group']);

it('appends automatic sessions on the view page even when the child resource overrides getRelations', function (): void {
    registerUserSessionsResource(UserSessionsCustomRelationsResource::class);
    $owner = UserSessionsFixtures::user();
    $configured = UserSessionsSecondAuditManager::make(['marker' => 'custom page configuration']);
    $group = RelationGroup::make('Custom group', [UserSessionsThirdAuditManager::class]);
    UserSessionsCustomRelationsResource::$relations = [UserSessionsAuditManager::class, $configured, $group];
    $pageClass = userSessionsPage(UserSessionsCustomRelationsResource::class);
    $page = new $pageClass;
    $page->record = $owner;
    $relations = (new ReflectionMethod($pageClass, 'getAllRelationManagers'))->invoke($page);

    expect(UserSessionsCustomRelationsResource::getRelations())->toBe([UserSessionsAuditManager::class, $configured, $group])
        ->and($relations)->toBe([UserSessionsAuditManager::class, $configured, $group, UserSessionsRelationManager::class]);

    Livewire::test($pageClass, ['record' => $owner->getRouteKey()])
        ->assertSuccessful()->assertSee('Sessions')
        ->tap(fn (Testable $component) => expect(userSessionsClasses($component->instance()->getRelationManagers()))
            ->toBe([UserSessionsAuditManager::class, UserSessionsSecondAuditManager::class, UserSessionsThirdAuditManager::class, UserSessionsRelationManager::class]));
});

it('does not append sessions twice when a custom view page already has a grouped sessions configuration', function (): void {
    registerUserSessionsResource(UserSessionsCustomRelationsResource::class);
    $group = RelationGroup::make('Security', [UserSessionsRelationManager::make(['isLazy' => false])]);
    UserSessionsCustomRelationsResource::$relations = [UserSessionsAuditManager::class, $group];
    $pageClass = userSessionsPage(UserSessionsCustomRelationsResource::class);
    $page = new $pageClass;
    $page->record = UserSessionsFixtures::user();

    expect((new ReflectionMethod($pageClass, 'getAllRelationManagers'))->invoke($page))
        ->toBe([UserSessionsAuditManager::class, $group]);
});

it('detects supported models without inspecting the database schema', function (): void {
    Schema::connection('testing')->drop('user_sessions_sessions');
    Schema::connection('secondary')->drop('user_sessions_sessions');
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeTrue()
        ->and(UserSessions::supportsModel(UserSessionsPlainModel::class))->toBeFalse()
        ->and(UserSessions::supportsModel(UserSessionsOtherUser::class))->toBeFalse()
        ->and($queries)->toBe([]);
});

it('does not attach sessions for a non-authenticatable resource or any non-database session driver', function (string $driver, bool $plainModel): void {
    config(['session.driver' => $driver]);
    $resource = $plainModel ? UserSessionsPlainResource::class : UserSessionsResource::class;
    registerUserSessionsResource($resource);

    expect($resource::getRelations())->toBe([])
        ->and(UserSessions::supportsModel($resource::getModel()))->toBeFalse();
})->with([
    'plain Eloquent model' => ['database', true],
    'array sessions' => ['array', false],
    'file sessions' => ['file', false],
    'redis sessions' => ['redis', false],
    'cookie sessions' => ['cookie', false],
]);

it('uses the active panel guard instead of the default guard to identify its Eloquent user model', function (): void {
    config([
        'auth.providers.other' => ['driver' => 'eloquent', 'model' => UserSessionsOtherUser::class],
        'auth.guards.web.provider' => 'other',
    ]);
    Auth::forgetGuards();
    $this->actingAs(UserSessionsFixtures::user(UserSessionsOtherUser::class), 'web');
    $this->actingAs(UserSessionsFixtures::user(), 'panel');
    Auth::shouldUse('web');

    // Even though the active panel has the correct provider, mixed session guards are unsafe.
    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeFalse()
        ->and(UserSessionsResource::getRelations())->toBe([]);

    config(['auth.guards.web.driver' => 'token']);
    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeTrue()
        ->and(UserSessionsResource::getRelations())->toBe([UserSessionsRelationManager::class]);
});

it('allows several session guards only when they all use the exact same Eloquent model', function (): void {
    config([
        'auth.providers.same_users' => ['driver' => 'eloquent', 'model' => UserSessionsUser::class],
        'auth.guards.additional' => ['driver' => 'session', 'provider' => 'same_users'],
    ]);

    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeTrue();

    config(['auth.providers.same_users.model' => UserSessionsOtherUser::class]);
    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeFalse();
});

it('fails closed for unsupported active panel guards providers and exact model mismatches', function (string $case): void {
    match ($case) {
        'non-session guard' => config(['auth.guards.panel.driver' => 'token']),
        'missing guard' => config(['auth.guards.panel' => null]),
        'missing provider' => config(['auth.guards.panel.provider' => 'missing']),
        'non-eloquent provider' => config(['auth.providers.users.driver' => 'database']),
        'plain provider model' => config(['auth.providers.users.model' => UserSessionsPlainModel::class]),
        'different provider model' => config(['auth.providers.users.model' => UserSessionsOtherUser::class]),
        'mixed non-eloquent guard' => config([
            'auth.providers.legacy' => ['driver' => 'database', 'table' => 'user_sessions_users'],
            'auth.guards.legacy' => ['driver' => 'session', 'provider' => 'legacy'],
        ]),
    };
    Auth::forgetGuards();

    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeFalse()
        ->and(UserSessionsResource::getRelations())->toBe([]);
})->with([
    'non-session guard', 'missing guard', 'missing provider', 'non-eloquent provider',
    'plain provider model', 'different provider model', 'mixed non-eloquent guard',
]);

it('omits the manager at runtime when the configured session table is missing', function (): void {
    $owner = UserSessionsFixtures::user();
    $page = userSessionsPage();
    expect(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeTrue();
    Schema::connection('testing')->drop('user_sessions_sessions');

    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeTrue()
        ->and(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeFalse();

    Livewire::test($page, ['record' => $owner->getRouteKey()])->assertSuccessful()
        ->tap(fn (Testable $component) => expect(userSessionsClasses($component->instance()->getRelationManagers()))
            ->not->toContain(UserSessionsRelationManager::class));

    UserSessionsFixtures::createSessionTable('testing');
    expect(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeTrue();
});

it('fails closed at runtime when the session table is missing required metadata columns', function (): void {
    $owner = UserSessionsFixtures::user();
    Schema::connection('testing')->table('user_sessions_sessions', function (\Illuminate\Database\Schema\Blueprint $table): void {
        $table->dropColumn('ip_address');
    });

    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeTrue()
        ->and(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage()))->toBeFalse();
});

it('queries all stored owner sessions regardless of age without loading payloads other users or guests', function (): void {
    $owner = UserSessionsFixtures::user();
    $other = UserSessionsFixtures::user();
    $active = UserSessionsFixtures::session($owner);
    $boundary = UserSessionsFixtures::session($owner, ['last_activity' => now()->subMinutes(30)->timestamp]);
    $expired = UserSessionsFixtures::session($owner, ['last_activity' => now()->subMinutes(30)->subSecond()->timestamp]);
    $outside = UserSessionsFixtures::session($other);
    $guest = UserSessionsFixtures::session(null);
    $query = UserSessions::queryFor($owner);
    $records = $query->get();

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($records->modelKeys())->toEqualCanonicalizing([$active, $boundary, $expired])
        ->and($records->modelKeys())->not->toContain($outside, $guest);
    foreach ($records as $record) {
        expect($record)->toBeInstanceOf(DatabaseSession::class)
            ->and($record->getAttributes())->not->toHaveKey('payload');
    }
});

it('never treats an unsaved or non-authenticatable owner as a guest session query', function (): void {
    $guest = UserSessionsFixtures::session(null);
    $owner = new UserSessionsUser;
    $owner->forceFill(['id' => 987654321, 'name' => 'Unsaved owner']);
    UserSessionsFixtures::session(null, ['user_id' => $owner->getAuthIdentifier()]);

    expect(UserSessions::queryFor($owner)->exists())->toBeFalse()
        ->and(UserSessions::queryFor(new UserSessionsPlainModel)->exists())->toBeFalse()
        ->and(userSessionsExists($guest))->toBeTrue();
});

it('does not filter stored sessions by age or require a valid configured lifetime', function (int | string | null $lifetime, bool $missing = false): void {
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner, ['last_activity' => now()->subMinutes(20)->timestamp]);
    expect(UserSessions::queryFor($owner)->pluck('id')->all())->toBe([$id]);
    $expired = UserSessionsFixtures::session($owner, ['last_activity' => now()->subYear()->timestamp]);
    if ($missing) {
        $sessionConfig = config('session');
        unset($sessionConfig['lifetime']);
        config(['session' => $sessionConfig]);
        expect(config()->has('session.lifetime'))->toBeFalse();
    } else {
        config(['session.lifetime' => $lifetime]);
    }

    expect(UserSessions::supportsModel(UserSessionsUser::class))->toBeTrue()
        ->and(UserSessionsResource::getRelations())->toContain(UserSessionsRelationManager::class)
        ->and(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage()))->toBeTrue()
        ->and(UserSessions::queryFor($owner)->pluck('id')->all())->toEqualCanonicalizing([$id, $expired]);
    userSessionsManager($owner)->assertCountTableRecords(2)
        ->assertCanSeeTableRecords([userSessionsRecord($id), userSessionsRecord($expired)]);
})->with([
    'shorter lifetime' => [10],
    'exact lifetime boundary' => [20],
    'longer lifetime' => [120],
    'numeric string lifetime' => ['10'],
    'zero lifetime' => [0],
    'negative lifetime' => [-1],
    'invalid lifetime' => ['invalid'],
    'null lifetime' => [null],
    'missing lifetime' => [null, true],
]);

it('uses the authentication identifier rather than the primary key for session ownership and revocation', function (): void {
    config(['auth.providers.users.model' => UserSessionsIdentifierUser::class]);
    Auth::forgetGuards();
    registerUserSessionsResource(UserSessionsIdentifierResource::class);
    $this->actingAs(UserSessionsFixtures::user(UserSessionsIdentifierUser::class), 'panel');
    $owner = UserSessionsFixtures::user(UserSessionsIdentifierUser::class, ['auth_identifier' => 81234567]);
    $active = UserSessionsFixtures::session($owner);
    $primaryKeyCollision = UserSessionsFixtures::session(null, ['user_id' => $owner->getKey()]);

    expect($owner->getAuthIdentifier())->not->toBe($owner->getKey())
        ->and(UserSessions::queryFor($owner)->pluck('id')->all())->toBe([$active]);
    $manager = userSessionsManager($owner, UserSessionsIdentifierResource::class)
        ->assertCanSeeTableRecords([userSessionsRecord($active)])
        ->assertCanNotSeeTableRecords([userSessionsRecord($primaryKeyCollision)]);
    $manager->callAction(TestAction::make('revoke')->table(UserSessions::recordKey(userSessionsRecord($active), $owner)));

    expect(userSessionsExists($active))->toBeFalse()
        ->and(userSessionsExists($primaryKeyCollision))->toBeTrue();
});

it('creates a session model using the configured table connection and native database prefix', function (): void {
    UserSessionsFixtures::createSessionTable('secondary', 'user_sessions_runtime_sessions');
    config(['session.connection' => 'secondary', 'session.table' => 'user_sessions_runtime_sessions']);
    $owner = UserSessionsFixtures::user();
    $configured = UserSessionsFixtures::session($owner);
    $defaultDecoy = UserSessionsFixtures::session($owner, connection: 'testing', table: 'user_sessions_sessions');
    $model = UserSessions::newModel();

    expect($model)->toBeInstanceOf(DatabaseSession::class)
        ->and($model->getTable())->toBe('user_sessions_runtime_sessions')
        ->and($model->getConnection()->getName())->toBe('secondary')
        ->and($model->getConnection()->getTablePrefix())->toBe('secondary_')
        ->and($model->getKeyType())->toBe('string')
        ->and($model->getIncrementing())->toBeFalse()
        ->and($model->usesTimestamps())->toBeFalse()
        ->and(UserSessions::queryFor($owner)->pluck('id')->all())->toBe([$configured]);

    $manager = userSessionsManager($owner)->assertCountTableRecords(1);
    $manager->callAction(TestAction::make('revoke')->table(UserSessions::recordKey(userSessionsRecord($configured), $owner)));
    expect(userSessionsExists($configured))->toBeFalse()
        ->and(userSessionsExists($defaultDecoy, 'testing', 'user_sessions_sessions'))->toBeTrue();
});

it('resolves a null session connection from the current default database rather than the owner connection', function (): void {
    $owner = UserSessionsFixtures::user(connection: 'secondary');
    $defaultSession = UserSessionsFixtures::session($owner, connection: 'testing');
    $ownerConnectionDecoy = UserSessionsFixtures::session($owner, connection: 'secondary');
    config(['session.connection' => null]);

    expect(UserSessions::newModel()->getConnection()->getName())->toBe('testing')
        ->and(UserSessions::queryFor($owner)->pluck('id')->all())->toBe([$defaultSession]);

    config(['database.default' => 'secondary']);
    expect(UserSessions::newModel()->getConnection()->getName())->toBe('secondary')
        ->and(UserSessions::queryFor($owner)->pluck('id')->all())->toBe([$ownerConnectionDecoy]);

    config(['session.connection' => 'testing']);
    expect(UserSessions::newModel()->getConnection()->getName())->toBe('testing')
        ->and(UserSessions::queryFor($owner)->pluck('id')->all())->toBe([$defaultSession]);
});

it('checks the runtime schema on the session connection instead of the owner connection or default table', function (): void {
    $owner = UserSessionsFixtures::user();
    UserSessionsFixtures::createSessionTable('secondary', 'user_sessions_runtime_sessions');
    config(['session.connection' => 'secondary', 'session.table' => 'user_sessions_runtime_sessions']);

    expect(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage()))->toBeTrue();
    Schema::connection('secondary')->drop('user_sessions_runtime_sessions');
    expect(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage()))->toBeFalse();
});

it('uses opaque deterministic owner-bound HMAC record keys instead of raw session identifiers', function (): void {
    $owner = UserSessionsFixtures::user();
    $other = UserSessionsFixtures::user();
    $record = userSessionsRecord(UserSessionsFixtures::session($owner));
    $second = userSessionsRecord(UserSessionsFixtures::session($owner));
    $key = UserSessions::recordKey($record, $owner);

    expect($key)->toMatch('/\A[a-f0-9]{64}\z/')
        ->not->toBe($record->getKey())
        ->not->toBe(hash('sha256', $record->getKey()))
        ->and(UserSessions::recordKey($record, $owner))->toBe($key)
        ->and(UserSessions::recordKey($record, $other))->not->toBe($key)
        ->and(UserSessions::recordKey($second, $owner))->not->toBe($key);

    config(['app.key' => 'base64:' . base64_encode(str_repeat('K', 32))]);

    expect(UserSessions::recordKey($record, $owner))->not->toBe($key);
});

it('requires an actual registered AutoResource and an owner of exactly its model class', function (string $case): void {
    $owner = UserSessionsFixtures::user();
    $page = userSessionsPage();
    switch ($case) {
        case 'native resource':
            Filament::setCurrentPanel(Panel::make()->id('native-sessions')->resources([UserSessionsNativeResource::class]));
            $page = UserSessionsNativeView::class;
            break;
        case 'unregistered resource':
            Filament::setCurrentPanel(Panel::make()->id('unregistered-sessions')->authGuard('panel')->resources([]));
            break;
        case 'owner subclass':
            $owner = UserSessionsFixtures::user(UserSessionsOtherUser::class);
            break;
        case 'plain model owner':
            $owner = UserSessionsPlainModel::query()->findOrFail($owner->getKey());
            break;
    }

    expect(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeFalse();
})->with(['native resource', 'unregistered resource', 'owner subclass', 'plain model owner']);

it('authorizes and revokes sessions for an actively configured-only user resource without changing its configuration', function (): void {
    $panel = registerConfiguredUserSessionsResource();
    $resource = UserSessionsConfiguredResource::class;
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner);
    $page = userSessionsPage($resource);
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);

    expect($panel->getResources())->not->toContain($resource)
        ->and(Filament::getResourceConfiguration($resource))->toBe($panel->getResourceConfiguration($resource, 'visible'))
        ->and(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeTrue();
    Livewire::test($page, ['record' => $owner->getRouteKey()])->assertSuccessful()->assertSee('Sessions');
    $manager = userSessionsManager($owner, $resource)->assertCanSeeTableRecords([userSessionsRecord($id)])
        ->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    $manager->call('callMountedAction')->assertSuccessful();

    expect(userSessionsExists($id))->toBeFalse()
        ->and(Filament::getCurrentResourceConfigurationKey())->toBe('visible');
});

it('enforces the active resource configuration query scope instead of another registered configuration', function (string $activeKey): void {
    registerConfiguredUserSessionsResource($activeKey);
    $resource = UserSessionsConfiguredResource::class;
    $owner = UserSessionsFixtures::user(attributes: ['visible' => $activeKey === 'visible']);
    $outside = UserSessionsFixtures::user(attributes: ['visible' => $activeKey !== 'visible']);
    $id = UserSessionsFixtures::session($owner);
    $page = userSessionsPage($resource);

    expect($resource::getEloquentQuery()->whereKey($outside->getKey())->exists())->toBeFalse()
        ->and(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeTrue()
        ->and(UserSessionsRelationManager::canViewForRecord($outside, $page))->toBeFalse();
    userSessionsManager($owner, $resource)->assertCanSeeTableRecords([userSessionsRecord($id)]);
    Livewire::test(UserSessionsRelationManager::class, ['ownerRecord' => $outside, 'pageClass' => $page])->assertForbidden();

    expect(Filament::getCurrentResourceConfigurationKey())->toBe($activeKey);
})->with(['visible', 'hidden']);

it('rejects configured-only resources without their own active registered configuration', function (?string $activeKey): void {
    registerConfiguredUserSessionsResource($activeKey);
    $owner = UserSessionsFixtures::user();
    $page = userSessionsPage(UserSessionsConfiguredResource::class);

    expect(Filament::getResourceConfiguration(UserSessionsConfiguredResource::class))->toBeNull()
        ->and(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeFalse();
    if ($activeKey === 'unrelated') {
        expect(Filament::getResourceConfiguration(UserSessionsResource::class))->not->toBeNull();
    }
    Livewire::test(UserSessionsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => $page])->assertForbidden();

    expect(Filament::getCurrentResourceConfigurationKey())->toBe($activeKey);
})->with([
    'no active configuration' => [null],
    'unregistered configuration key' => ['missing'],
    'configuration of another resource' => ['unrelated'],
]);

it('reauthorizes the active resource configuration after revoke confirmation', function (?string $activeKey): void {
    registerConfiguredUserSessionsResource();
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner);
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);
    $manager = userSessionsManager($owner, UserSessionsConfiguredResource::class)
        ->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    Filament::setCurrentResourceConfigurationKey($activeKey);
    $manager->call('callMountedAction')->assertForbidden();

    expect(userSessionsExists($id))->toBeTrue()
        ->and(Filament::getCurrentResourceConfigurationKey())->toBe($activeKey);
})->with([
    'no active configuration' => [null],
    'unregistered configuration key' => ['missing'],
    'configuration of another resource' => ['unrelated'],
    'configuration excluding this owner' => ['hidden'],
]);

it('requires the exact owner model selected by the active resource configuration', function (): void {
    registerConfiguredUserSessionsResource('other-model');
    $resource = UserSessionsConfiguredResource::class;
    $owner = UserSessionsFixtures::user();
    $page = userSessionsPage($resource);

    expect($resource::getModel())->toBe(UserSessionsOtherUser::class)
        ->and(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeFalse();
    Livewire::test(UserSessionsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => $page])->assertForbidden();

    config(['auth.providers.users.model' => UserSessionsOtherUser::class]);
    Auth::forgetGuards();
    Gate::policy(UserSessionsOtherUser::class, UserSessionsPolicy::class);
    $configuredOwner = UserSessionsFixtures::user(UserSessionsOtherUser::class);
    $this->actingAs(UserSessionsFixtures::user(UserSessionsOtherUser::class), 'panel');
    $id = UserSessionsFixtures::session($configuredOwner);

    expect(UserSessionsRelationManager::canViewForRecord($configuredOwner, $page))->toBeTrue()
        ->and(UserSessionsRelationManager::canViewForRecord($owner, $page))->toBeFalse();
    userSessionsManager($configuredOwner, $resource)->assertCanSeeTableRecords([userSessionsRecord($id)]);
    expect(Filament::getCurrentResourceConfigurationKey())->toBe('other-model');
});

it('registers configured-only automatic session relations without inspecting the database schema', function (): void {
    Schema::connection('testing')->drop('user_sessions_sessions');
    Schema::connection('secondary')->drop('user_sessions_sessions');
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    registerConfiguredUserSessionsResource();

    expect(UserSessionsConfiguredResource::getRelations())->toBe([UserSessionsRelationManager::class])
        ->and($queries)->toBe([])
        ->and(Filament::getCurrentResourceConfigurationKey())->toBe('visible');
});

it('requires an authenticated actor on the active panel guard rather than merely the default guard', function (): void {
    $owner = UserSessionsFixtures::user();
    Auth::guard('panel')->forgetUser();
    $this->actingAs(UserSessionsFixtures::user(), 'web');

    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('panel')->check())->toBeFalse()
        ->and(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage()))->toBeFalse();
    Livewire::test(UserSessionsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => userSessionsPage()])
        ->assertForbidden();
});

it('enforces owner view policies and the resource query scope before showing sessions', function (string $restriction): void {
    $owner = UserSessionsFixtures::user();
    if ($restriction === 'view policy') {
        UserSessionsPolicy::$view = false;
        expect(UserSessionsResource::canView($owner))->toBeFalse();
    } else {
        $owner->update(['visible' => false]);
        UserSessionsResource::$restrictQuery = true;
        expect(UserSessionsResource::canView($owner))->toBeTrue()
            ->and(UserSessionsResource::getEloquentQuery()->whereKey($owner)->exists())->toBeFalse();
    }

    expect(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage()))->toBeFalse();
    Livewire::test(UserSessionsRelationManager::class, ['ownerRecord' => $owner, 'pageClass' => userSessionsPage()])
        ->assertForbidden();
})->with(['view policy', 'resource query scope']);

it('does not expose sessions belonging to an owner outside the active tenant', function (): void {
    $panel = registerUserSessionsResource(UserSessionsTenantResource::class);
    $panel->tenant(UserSessionsTeam::class, ownershipRelationship: 'team');
    $team = UserSessionsTeam::create(['name' => 'Current tenant']);
    $otherTeam = UserSessionsTeam::create(['name' => 'Other tenant']);
    $inside = UserSessionsFixtures::user(attributes: ['team_id' => $team->getKey()]);
    $outside = UserSessionsFixtures::user(attributes: ['team_id' => $otherTeam->getKey()]);
    Filament::setTenant($team, isQuiet: true);
    $page = userSessionsPage(UserSessionsTenantResource::class);

    expect(UserSessionsTenantResource::getEloquentQuery()->whereKey($outside)->exists())->toBeFalse()
        ->and(UserSessionsRelationManager::canViewForRecord($inside, $page))->toBeTrue()
        ->and(UserSessionsRelationManager::canViewForRecord($outside, $page))->toBeFalse();
    Livewire::test(UserSessionsRelationManager::class, ['ownerRecord' => $outside, 'pageClass' => $page])->assertForbidden();
});

it('reauthorizes existing Livewire managers during hydration', function (string $restriction): void {
    $owner = UserSessionsFixtures::user();
    $manager = userSessionsManager($owner);
    match ($restriction) {
        'view policy' => UserSessionsPolicy::$view = false,
        'resource scope' => [$owner->update(['visible' => false]), UserSessionsResource::$restrictQuery = true],
        'missing session table' => Schema::connection('testing')->drop('user_sessions_sessions'),
        'unregistered resource' => Filament::setCurrentPanel(Panel::make()->id('removed-sessions')->authGuard('panel')->resources([])),
        'changed driver' => config(['session.driver' => 'array']),
    };

    $manager->call('$refresh')->assertForbidden();
})->with(['view policy', 'resource scope', 'missing session table', 'unregistered resource', 'changed driver']);

it('offers only session metadata columns and a confirmed revoke action without mutation export or bulk actions', function (): void {
    $owner = UserSessionsFixtures::user();
    UserSessionsFixtures::session($owner);
    $table = userSessionsManager($owner)->instance()->getTable();

    expect(UserSessionsRelationManager::getTitle($owner, userSessionsPage()))->toBe('Sessions')
        ->and(array_keys($table->getColumns()))->toBe(['ip_address', 'user_agent', 'last_activity'])
        ->and($table->getColumn('ip_address'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('user_agent'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('last_activity'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('is_current'))->toBeNull()
        ->and($table->getHeaderActions())->toBe([])
        ->and($table->getToolbarActions())->toBe([])
        ->and($table->getBulkActions())->toBe([])
        ->and(array_map(fn (Action $action): string => $action->getName(), $table->getRecordActions()))->toBe(['revoke']);
});

it('returns no selected session records or selectable keys for forged selection state without querying the database', function (bool $trackingDeselected, bool $fetchRecords, ?int $chunkSize): void {
    $owner = UserSessionsFixtures::user();
    $active = UserSessionsFixtures::session($owner);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $expired = UserSessionsFixtures::session($owner, ['last_activity' => now()->subHour()->timestamp]);
    $guest = UserSessionsFixtures::session(null);
    $manager = userSessionsManager($owner)->instance();
    $manager->isTrackingDeselectedTableRecords = $trackingDeselected;
    $manager->deselectedTableRecords = [];
    $manager->selectedTableRecords = $trackingDeselected ? [] : [$active, $outside, $expired, $guest];
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    // Direct calls exercise the override even on Filament versions that block these RPCs.
    $selected = $manager->getSelectedTableRecords($fetchRecords, $chunkSize);
    expect($selected)->toBeInstanceOf(SupportCollection::class)
        ->and($selected->all())->toBe([])
        ->and($manager->getAllSelectableTableRecordKeys())->toBe([])
        ->and($manager->getGroupedSelectableTableRecordKeys(null))->toBe([])
        ->and($manager->getGroupedSelectableTableRecordKeys('203.0.113.42'))->toBe([])
        ->and($queries)->toBe([]);
})->with([
    'all except deselected' => [true],
    'explicit raw selections' => [false],
])->with([
    'fetch models' => [true],
    'fetch keys' => [false],
])->with([
    'unchunked' => [null],
    'chunked' => [2],
]);

it('never returns raw session identifiers from native Livewire selection endpoints', function (string $method, array $arguments): void {
    $owner = UserSessionsFixtures::user();
    $active = UserSessionsFixtures::session($owner);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $payload = 'PRIVATE-SELECTION-ENDPOINT-PAYLOAD';
    $second = UserSessionsFixtures::session($owner, ['payload' => $payload]);
    $manager = userSessionsManager($owner)
        ->set('isTrackingDeselectedTableRecords', true)
        ->set('deselectedTableRecords', []);
    $manager->call($method, ...$arguments);

    if ($method === 'getAllSelectableTableRecordKeys' || array_key_exists('returns', $manager->effects)) {
        $manager->assertSuccessful();
        expect($manager->effects['returns'])->toBe([[]]);
        assertUserSessionsPrivate($manager, [$active, $second, $outside, $payload]);
    } else {
        // Newer Filament versions reject inherited Unexposed methods before dispatch.
        $manager->assertForbidden();
    }
})->with([
    'all selectable keys' => ['getAllSelectableTableRecordKeys', []],
    'ungrouped selectable keys' => ['getGroupedSelectableTableRecordKeys', [null]],
    'grouped selectable keys' => ['getGroupedSelectableTableRecordKeys', ['203.0.113.42']],
    'selected record models' => ['getSelectedTableRecords', [true]],
    'selected record keys' => ['getSelectedTableRecords', [false]],
    'chunked selected record keys' => ['getSelectedTableRecords', [false, 2]],
]);

it('clamps tampered pagination state to an allowed integer page size before fetching session records', function (int | string $requested, ?int $expected): void {
    $owner = UserSessionsFixtures::user();
    for ($index = 0; $index < 61; $index++) {
        UserSessionsFixtures::session($owner);
    }
    $manager = userSessionsManager($owner)->instance();

    // Bypass the Livewire updated hook so the getter itself must enforce the limit.
    $manager->tableRecordsPerPage = $requested;
    $manager->flushCachedTableRecords();
    $perPage = $manager->getTableRecordsPerPage();
    expect($perPage)->toBeInt()
        ->and(in_array($perPage, [10, 25, 50], true))->toBeTrue();
    if ($expected !== null) {
        expect($perPage)->toBe($expected);
    }
    $records = $manager->getTableRecords();
    expect($records->count())->toBe($perPage)->toBeLessThanOrEqual(50);
})->with([
    'all records' => ['all', null],
    'huge integer' => [PHP_INT_MAX, null],
    'huge numeric string' => ['1000000', null],
    'zero' => [0, null],
    'negative' => [-1, null],
    'arbitrary positive limit' => [11, null],
    'integer ten' => [10, 10],
    'string ten' => ['10', 10],
    'integer twenty-five' => [25, 25],
    'string twenty-five' => ['25', 25],
    'integer fifty' => [50, 50],
    'string fifty' => ['50', 50],
]);

it('keeps mount hydration and revoke visibility query counts bounded as visible sessions grow from one to ten', function (): void {
    $owners = [
        'one' => UserSessionsFixtures::user(),
        'ten' => UserSessionsFixtures::user(),
    ];
    $recordCounts = ['one' => 1, 'ten' => 10];
    foreach ($owners as $label => $owner) {
        for ($index = 0; $index < $recordCounts[$label]; $index++) {
            UserSessionsFixtures::session($owner);
        }
    }
    $phase = null;
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$phase, &$queries): void {
        if ($phase !== null) {
            $queries[$phase][] = $query->sql;
        }
    });

    foreach ($owners as $label => $owner) {
        $phase = $label . '-mount';
        $component = userSessionsManager($owner);
        $phase = $label . '-hydrate';
        $component->call('$refresh')->assertSuccessful();
        $phase = null;
        $manager = $component->instance();
        $records = $manager->getTableRecords();
        expect($records->count())->toBe($recordCounts[$label]);

        $phase = $label . '-visibility';
        foreach ($records as $record) {
            // Bind the already owner-scoped row instead of resolving an action key with another query.
            $action = clone $manager->getTable()->getAction('revoke');
            $action->record($record);
            expect($action->isVisible())->toBeTrue()
                ->and($action->isDisabled())->toBeFalse();
        }
        $phase = null;
    }

    expect($queries['one-mount'] ?? [])->not->toBeEmpty();
    foreach (['mount', 'hydrate', 'visibility'] as $operation) {
        expect(count($queries['ten-' . $operation] ?? []))
            ->toBeLessThanOrEqual(count($queries['one-' . $operation] ?? []) + 3);
    }
});

it('keeps payloads and raw session identifiers out of complete HTML and snapshots before and after confirmation', function (): void {
    $owner = UserSessionsFixtures::user();
    $current = str_repeat('C', 40);
    app('session')->driver()->setId($current);
    app('request')->setLaravelSession(app('session')->driver());
    $payload = 'DO-NOT-EXPOSE-THIS-SESSION-PAYLOAD';
    UserSessionsFixtures::session($owner, ['id' => $current, 'payload' => $payload, 'user_agent' => 'Current browser']);
    $active = UserSessionsFixtures::session($owner, ['payload' => $payload, 'user_agent' => 'Other browser']);
    $expired = UserSessionsFixtures::session($owner, ['last_activity' => now()->subHour()->timestamp]);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $guest = UserSessionsFixtures::session(null);
    $record = userSessionsRecord($active);
    $currentRecord = userSessionsRecord($current);
    $key = UserSessions::recordKey($record, $owner);
    $manager = userSessionsManager($owner)->assertCountTableRecords(3)
        ->assertCanSeeTableRecords([$record, $currentRecord, userSessionsRecord($expired)])
        ->assertCanNotSeeTableRecords([userSessionsRecord($outside), userSessionsRecord($guest)])
        ->assertSee('203.0.113.42')->assertSee('Other browser')->assertSee('5 minutes ago');
    expect($manager->instance()->getTableRecordKey($record))->toBe($key);
    $secrets = DB::connection('testing')->table('user_sessions_sessions')->get(['id', 'payload'])
        ->flatMap(fn ($session): array => [$session->id, $session->payload])->all();
    assertUserSessionsPrivate($manager, $secrets);
    $manager->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    expect(userSessionsExists($active))->toBeTrue();
    assertUserSessionsPrivate($manager, $secrets);
    $manager->call('callMountedAction')->assertSuccessful()->assertCountTableRecords(2)
        ->assertCanSeeTableRecords([$currentRecord, userSessionsRecord($expired)]);
    expect(userSessionsExists($active))->toBeFalse();
    assertUserSessionsPrivate($manager, $secrets);
});

it('revokes only the confirmed owner session even on a natively read-only ViewRecord page', function (): void {
    $owner = UserSessionsFixtures::user();
    $first = UserSessionsFixtures::session($owner);
    $second = UserSessionsFixtures::session($owner);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $key = UserSessions::recordKey(userSessionsRecord($first), $owner);

    expect(Filament::getCurrentPanel()->hasReadOnlyRelationManagersOnResourceViewPagesByDefault())->toBeTrue()
        ->and(UserSessionsResource::isReadOnly())->toBeFalse();
    $manager = userSessionsManager($owner)
        ->assertActionVisible(TestAction::make('revoke')->table($key))
        ->assertActionExists(TestAction::make('revoke')->table($key), fn (Action $action): bool => $action->isConfirmationRequired())
        ->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    expect(userSessionsExists($first))->toBeTrue();
    $manager->call('callMountedAction')->assertSuccessful();
    expect(userSessionsExists($first))->toBeFalse()
        ->and(userSessionsExists($second))->toBeTrue()
        ->and(userSessionsExists($outside))->toBeTrue();
});

it('revokes an expired stored session only after confirmation without deleting other rows', function (): void {
    $owner = UserSessionsFixtures::user();
    $expired = UserSessionsFixtures::session($owner, ['last_activity' => now()->subHour()->timestamp]);
    $other = UserSessionsFixtures::session($owner);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $guest = UserSessionsFixtures::session(null);
    $key = UserSessions::recordKey(userSessionsRecord($expired), $owner);
    $manager = userSessionsManager($owner)->assertCountTableRecords(2)
        ->assertCanSeeTableRecords([userSessionsRecord($expired)])
        ->assertActionVisible(TestAction::make('revoke')->table($key))
        ->assertActionEnabled(TestAction::make('revoke')->table($key))
        ->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    expect(userSessionsExists($expired))->toBeTrue();
    $manager->call('callMountedAction')->assertSuccessful()->assertCountTableRecords(1);

    expect(userSessionsExists($expired))->toBeFalse()
        ->and(userSessionsExists($other))->toBeTrue()
        ->and(userSessionsExists($outside))->toBeTrue()
        ->and(userSessionsExists($guest))->toBeTrue();
});

it('treats the administrator current session like any other owner row when revoking only the selected session', function (string $selected): void {
    $owner = Auth::guard('panel')->user();
    $current = str_repeat('D', 40);
    app('session')->driver()->setId($current);
    app('request')->setLaravelSession(app('session')->driver());
    UserSessionsFixtures::session($owner, ['id' => $current]);
    $other = UserSessionsFixtures::session($owner);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $guest = UserSessionsFixtures::session(null);
    $id = $selected === 'current session' ? $current : $other;
    $unselected = $selected === 'current session' ? $other : $current;
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);
    $manager = userSessionsManager($owner)->assertCountTableRecords(2)
        ->assertCanSeeTableRecords([userSessionsRecord($current), userSessionsRecord($other)])
        ->assertActionVisible(TestAction::make('revoke')->table($key))
        ->assertActionEnabled(TestAction::make('revoke')->table($key));
    userSessionsAttemptRevoke($manager, $key);
    $manager->assertSuccessful()->assertCountTableRecords(1);

    expect(userSessionsExists($id))->toBeFalse()
        ->and(userSessionsExists($unselected))->toBeTrue()
        ->and(userSessionsExists($outside))->toBeTrue()
        ->and(userSessionsExists($guest))->toBeTrue();
})->with(['current session', 'other session']);

it('rejects forged missing raw cross-owner and guest session action identifiers without deletion', function (string $case): void {
    $owner = UserSessionsFixtures::user();
    $active = UserSessionsFixtures::session($owner, ['id' => str_repeat('a', 64)]);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $expired = UserSessionsFixtures::session($owner, ['last_activity' => now()->subHour()->timestamp]);
    $guest = UserSessionsFixtures::session(null);
    $validKey = UserSessions::recordKey(userSessionsRecord($active), $owner);
    $key = match ($case) {
        'raw identifier' => $active,
        'tampered HMAC' => ($validKey[0] === 'f' ? '0' : 'f') . substr($validKey, 1),
        'missing HMAC' => str_repeat('0', 64),
        'missing raw identifier' => 'nonexistent-session',
        'other user' => UserSessions::recordKey(userSessionsRecord($outside), $owner),
        'different owner HMAC' => UserSessions::recordKey(userSessionsRecord($active), UserSessionsFixtures::user()),

        'guest session' => UserSessions::recordKey(userSessionsRecord($guest), $owner),
    };
    $manager = userSessionsManager($owner)->assertCountTableRecords(2);
    userSessionsAttemptRevoke($manager, $key);

    foreach ([$active, $outside, $expired, $guest] as $id) {
        expect(userSessionsExists($id))->toBeTrue();
    }
})->with([
    'raw identifier', 'tampered HMAC', 'missing HMAC', 'missing raw identifier',
    'other user', 'different owner HMAC', 'guest session',
]);

it('requires update permission and a writable resource before revoking an otherwise visible session', function (string $restriction): void {
    $resource = $restriction === 'read-only resource' ? UserSessionsReadOnlyResource::class : UserSessionsResource::class;
    registerUserSessionsResource($resource);
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner);
    if ($restriction === 'update policy') {
        UserSessionsPolicy::$edit = false;
    }

    expect(UserSessionsRelationManager::canViewForRecord($owner, userSessionsPage($resource)))->toBeTrue();
    $manager = userSessionsManager($owner, $resource)->assertCanSeeTableRecords([userSessionsRecord($id)]);
    userSessionsAttemptRevoke($manager, UserSessions::recordKey(userSessionsRecord($id), $owner));
    expect(userSessionsExists($id))->toBeTrue();
})->with(['update policy', 'read-only resource']);

it('rechecks update permissions after the revoke confirmation has mounted', function (): void {
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner);
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);
    $manager = userSessionsManager($owner)->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    UserSessionsPolicy::$edit = false;
    $manager->call('callMountedAction');

    expect(userSessionsExists($id))->toBeTrue();
});

it('rechecks owner view policy query scope and tenant after the revoke confirmation has mounted', function (string $restriction): void {
    $resource = $restriction === 'tenant' ? UserSessionsTenantResource::class : UserSessionsResource::class;
    $panel = registerUserSessionsResource($resource);
    $owner = UserSessionsFixtures::user();
    $otherTenant = null;
    if ($restriction === 'tenant') {
        $panel->tenant(UserSessionsTeam::class, ownershipRelationship: 'team');
        $tenant = UserSessionsTeam::create(['name' => 'Original tenant']);
        $otherTenant = UserSessionsTeam::create(['name' => 'Different tenant']);
        $owner->update(['team_id' => $tenant->getKey()]);
        Filament::setTenant($tenant, isQuiet: true);
    }
    $id = UserSessionsFixtures::session($owner);
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);
    $manager = userSessionsManager($owner, $resource)->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    match ($restriction) {
        'view policy' => UserSessionsPolicy::$view = false,
        'query scope' => [$owner->update(['visible' => false]), UserSessionsResource::$restrictQuery = true],
        'tenant' => Filament::setTenant($otherTenant, isQuiet: true),
    };
    $manager->call('callMountedAction')->assertForbidden();

    expect(userSessionsExists($id))->toBeTrue();
})->with(['view policy', 'query scope', 'tenant']);

it('rechecks session ownership after confirmation rather than deleting another owner or guest row', function (string $change): void {
    $owner = UserSessionsFixtures::user();
    $id = UserSessionsFixtures::session($owner, ['id' => str_repeat('E', 40)]);
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);
    $manager = userSessionsManager($owner)->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    $query = DB::connection('testing')->table('user_sessions_sessions')->where('id', $id);
    switch ($change) {
        case 'other owner':
            $query->update(['user_id' => UserSessionsFixtures::user()->getAuthIdentifier()]);
            break;
        case 'guest':
            $query->update(['user_id' => null]);
            break;

    }
    $manager->call('callMountedAction');

    expect(userSessionsExists($id))->toBeTrue();
})->with(['other owner', 'guest']);

it('revokes only the confirmed owner row even if it expires or matches the current session ID after confirmation', function (string $change): void {
    $owner = Auth::guard('panel')->user();
    $id = UserSessionsFixtures::session($owner, ['id' => str_repeat('G', 40)]);
    $other = UserSessionsFixtures::session($owner);
    $outside = UserSessionsFixtures::session(UserSessionsFixtures::user());
    $guest = UserSessionsFixtures::session(null);
    $key = UserSessions::recordKey(userSessionsRecord($id), $owner);
    $manager = userSessionsManager($owner)->mountAction(TestAction::make('revoke')->table($key))
        ->assertActionMounted(TestAction::make('revoke')->table($key));
    if ($change === 'expired') {
        DB::connection('testing')->table('user_sessions_sessions')->where('id', $id)
            ->update(['last_activity' => now()->subHour()->timestamp]);
    } else {
        app('session')->driver()->setId($id);
        app('request')->setLaravelSession(app('session')->driver());
    }
    $manager->call('callMountedAction')->assertSuccessful()->assertCountTableRecords(1);

    expect(userSessionsExists($id))->toBeFalse()
        ->and(userSessionsExists($other))->toBeTrue()
        ->and(userSessionsExists($outside))->toBeTrue()
        ->and(userSessionsExists($guest))->toBeTrue();
})->with(['expired', 'current session']);
