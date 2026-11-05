<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Throwable;

class UserFieldFixtures
{
    public static function create(string $connection = 'testing'): void
    {
        $schema = Schema::connection($connection);

        try {
            $schema->create('user_field_identities', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
            $schema->create('user_field_title_identities', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
            });
            $schema->create('user_field_records', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('author_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_id')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->unsignedBigInteger('title_user_id')->nullable();
                $table->unsignedBigInteger('named_user_id')->nullable();
                $table->unsignedBigInteger('accessor_user_id')->nullable();
                $table->nullableMorphs('subject');
            });
        } catch (Throwable $exception) {
            static::drop($connection);

            throw $exception;
        }
    }

    public static function drop(string $connection = 'testing'): void
    {
        $schema = Schema::connection($connection);
        $schema->dropIfExists('user_field_records');
        $schema->dropIfExists('user_field_title_identities');
        $schema->dropIfExists('user_field_identities');
    }
}

class UserFieldIdentity extends Authenticatable implements HasAvatar
{
    public $timestamps = false;

    protected $table = 'user_field_identities';

    protected $guarded = [];

    public function getFilamentAvatarUrl(): ?string
    {
        return '/user-field-avatars/' . $this->getKey() . '.svg';
    }
}

class UserFieldTitleIdentity extends UserFieldIdentity
{
    protected $table = 'user_field_title_identities';
}

class UserFieldNamedIdentity extends UserFieldTitleIdentity implements HasName
{
    public function getFilamentName(): string
    {
        return 'Display: ' . $this->title;
    }
}

class UserFieldAccessorIdentity extends UserFieldTitleIdentity
{
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => 'Accessor: ' . $this->title);
    }
}

class UserFieldRecord extends Model
{
    public $timestamps = false;

    protected $table = 'user_field_records';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserFieldIdentity::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserFieldIdentity::class, 'author_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(UserFieldIdentity::class, 'reviewed_by_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function titleUser(): BelongsTo
    {
        return $this->belongsTo(UserFieldTitleIdentity::class, 'title_user_id');
    }

    public function namedUser(): BelongsTo
    {
        return $this->belongsTo(UserFieldNamedIdentity::class, 'named_user_id');
    }

    public function accessorUser(): BelongsTo
    {
        return $this->belongsTo(UserFieldAccessorIdentity::class, 'accessor_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

class UserFieldNonUserRecord extends UserFieldRecord
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'user_id');
    }
}

class UserFieldOrphanRecord extends Model
{
    public $timestamps = false;

    protected $table = 'user_field_records';

    protected $guarded = [];
}

class UserFieldUnverifiedRecord extends UserFieldOrphanRecord
{
    public function user(): HasMany
    {
        return $this->hasMany(UserFieldIdentity::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserFieldIdentity::class, 'reviewed_by_id');
    }
}

class UserFieldAuthenticatableRecord extends UserFieldRecord implements AuthenticatableContract
{
    use AuthenticatableTrait;
}

class UserFieldOwner extends Owner
{
    protected $table = 'owners';

    public function userFieldRecords(): HasMany
    {
        return $this->hasMany(UserFieldRecord::class, 'owner_id');
    }
}

class UserFieldRecordResource extends AutoResource
{
    protected static ?string $model = UserFieldRecord::class;

    protected static array $searchableColumns = ['user.name' => 'both'];
}

class UserFieldListRecords extends ListRecords
{
    protected static string $resource = UserFieldRecordResource::class;

    public array $userFieldData = [];

    public function setUserFieldTable(Table $table): void
    {
        $this->table = $table;
    }
}
