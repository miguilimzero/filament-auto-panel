<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Miguilim\FilamentAutoPanel\AutoResource;

class UserSessionsFixtures
{
    public static function create(): void
    {
        foreach (['testing', 'secondary'] as $connection) {
            $schema = DatabaseSchema::connection($connection);
            $schema->create('user_sessions_users', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('auth_identifier')->unique();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name');
                $table->string('password')->default('');
                $table->boolean('visible')->default(true);
            });
            static::createSessionTable($connection);
        }

        DatabaseSchema::connection('testing')->create('user_sessions_teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DatabaseSchema::connection('testing')->create('user_sessions_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });
    }

    public static function createSessionTable(string $connection, string $table = 'user_sessions_sessions'): void
    {
        DatabaseSchema::connection($connection)->create($table, function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    public static function drop(): void
    {
        foreach (['testing', 'secondary'] as $connection) {
            foreach (['user_sessions_runtime_sessions', 'user_sessions_sessions', 'user_sessions_audit_entries', 'user_sessions_users', 'user_sessions_teams'] as $table) {
                DatabaseSchema::connection($connection)->dropIfExists($table);
            }
        }
    }

    public static function user(string $model = UserSessionsUser::class, array $attributes = [], ?string $connection = null): UserSessionsUser
    {
        $user = new $model;
        $user->setConnection($connection);
        $user->forceFill([
            'name' => 'Session owner',
            'auth_identifier' => random_int(100000, 999999999),
            ...$attributes,
        ])->save();

        return $user;
    }

    public static function session(?Model $owner, array $attributes = [], ?string $connection = null, ?string $table = null): string
    {
        $id = $attributes['id'] ?? 'private-session-' . bin2hex(random_bytes(20));
        DB::connection($connection ?? config('session.connection') ?? config('database.default'))
            ->table($table ?? config('session.table'))
            ->insert([
                'id' => $id,
                'user_id' => $owner?->getAuthIdentifier(),
                'ip_address' => '203.0.113.42',
                'user_agent' => 'Sessions test browser',
                'payload' => 'private-payload-' . bin2hex(random_bytes(16)),
                'last_activity' => now()->subMinutes(5)->timestamp,
                ...$attributes,
            ]);

        return $id;
    }
}

class UserSessionsUser extends Authenticatable
{
    protected $table = 'user_sessions_users';

    protected $guarded = [];

    protected $hidden = ['password'];

    public $timestamps = false;

    public function team(): BelongsTo
    {
        return $this->belongsTo(UserSessionsTeam::class, 'team_id');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(UserSessionsAuditEntry::class, 'user_id');
    }
}

class UserSessionsOtherUser extends UserSessionsUser {}

class UserSessionsIdentifierUser extends UserSessionsUser
{
    public function getAuthIdentifierName(): string
    {
        return 'auth_identifier';
    }
}

class UserSessionsPlainModel extends Model
{
    protected $table = 'user_sessions_users';

    public $timestamps = false;
}

class UserSessionsTeam extends Model
{
    protected $table = 'user_sessions_teams';

    protected $guarded = [];

    public $timestamps = false;
}

class UserSessionsAuditEntry extends Model
{
    protected $table = 'user_sessions_audit_entries';

    public $timestamps = false;
}

class UserSessionsPolicy
{
    public static bool $view = true;

    public static bool $edit = true;

    public function viewAny(UserSessionsUser $actor): bool
    {
        return true;
    }

    public function view(UserSessionsUser $actor, UserSessionsUser $owner): bool
    {
        return static::$view;
    }

    public function update(UserSessionsUser $actor, UserSessionsUser $owner): bool
    {
        return static::$edit;
    }
}

class UserSessionsResource extends AutoResource
{
    protected static ?string $model = UserSessionsUser::class;

    protected static ?string $slug = 'session-users';

    protected static ?string $recordTitleAttribute = 'name';

    public static bool $restrictQuery = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::$restrictQuery ? $query->where('visible', true) : $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}

class UserSessionsConfiguredResource extends UserSessionsResource
{
    protected static ?string $configurationClass = ResourceConfiguration::class;

    public static function getModel(): string
    {
        return static::getConfiguration()?->getKey() === 'other-model'
            ? UserSessionsOtherUser::class
            : parent::getModel();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return match (static::getConfiguration()?->getKey()) {
            'visible' => $query->where('visible', true),
            'hidden' => $query->where('visible', false),
            default => $query,
        };
    }
}

class UserSessionsCustomRelationsResource extends UserSessionsResource
{
    public static array $relations = [];

    public static function getRelations(): array
    {
        return static::$relations;
    }
}

class UserSessionsReadOnlyResource extends UserSessionsResource
{
    protected static bool $readOnly = true;
}

class UserSessionsIdentifierResource extends UserSessionsResource
{
    protected static ?string $model = UserSessionsIdentifierUser::class;
}

class UserSessionsPlainResource extends UserSessionsResource
{
    protected static ?string $model = UserSessionsPlainModel::class;
}

class UserSessionsTenantResource extends UserSessionsResource
{
    protected static ?string $tenantOwnershipRelationshipName = 'team';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Mirror the tenant scope applied by Filament middleware in a real panel request.
        return Filament::getTenant() ? static::scopeEloquentQueryToTenant($query, Filament::getTenant()) : $query;
    }
}

class UserSessionsNativeResource extends Resource
{
    protected static ?string $model = UserSessionsUser::class;
}

class UserSessionsNativeView extends ViewRecord
{
    protected static string $resource = UserSessionsNativeResource::class;
}

class UserSessionsAuditManager extends RelationManager
{
    protected static string $relationship = 'auditEntries';

    protected static ?string $title = 'Audit entries';

    public string $marker = '';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->columns([])->recordActions([]);
    }
}

class UserSessionsSecondAuditManager extends UserSessionsAuditManager {}

class UserSessionsThirdAuditManager extends UserSessionsAuditManager {}
