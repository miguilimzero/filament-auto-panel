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

class Issue17UserFieldFixtures
{
    public static function create(string $connection = 'testing'): void
    {
        $schema = Schema::connection($connection);

        try {
            $schema->create('issue17_identities', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
            $schema->create('issue17_title_identities', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
            });
            $schema->create('issue17_records', function (Blueprint $table): void {
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
        $schema->dropIfExists('issue17_records');
        $schema->dropIfExists('issue17_title_identities');
        $schema->dropIfExists('issue17_identities');
    }
}

class Issue17Identity extends Authenticatable implements HasAvatar
{
    public $timestamps = false;

    protected $table = 'issue17_identities';

    protected $guarded = [];

    public function getFilamentAvatarUrl(): ?string
    {
        return '/issue17-avatars/' . $this->getKey() . '.svg';
    }
}

class Issue17TitleIdentity extends Issue17Identity
{
    protected $table = 'issue17_title_identities';
}

class Issue17NamedIdentity extends Issue17TitleIdentity implements HasName
{
    public function getFilamentName(): string
    {
        return 'Display: ' . $this->title;
    }
}

class Issue17AccessorIdentity extends Issue17TitleIdentity
{
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => 'Accessor: ' . $this->title);
    }
}

class Issue17Record extends Model
{
    public $timestamps = false;

    protected $table = 'issue17_records';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Issue17Identity::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Issue17Identity::class, 'author_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Issue17Identity::class, 'reviewed_by_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function titleUser(): BelongsTo
    {
        return $this->belongsTo(Issue17TitleIdentity::class, 'title_user_id');
    }

    public function namedUser(): BelongsTo
    {
        return $this->belongsTo(Issue17NamedIdentity::class, 'named_user_id');
    }

    public function accessorUser(): BelongsTo
    {
        return $this->belongsTo(Issue17AccessorIdentity::class, 'accessor_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

class Issue17NonUserRecord extends Issue17Record
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'user_id');
    }
}

class Issue17OrphanRecord extends Model
{
    public $timestamps = false;

    protected $table = 'issue17_records';

    protected $guarded = [];
}

class Issue17UnverifiedRecord extends Issue17OrphanRecord
{
    public function user(): HasMany
    {
        return $this->hasMany(Issue17Identity::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Issue17Identity::class, 'reviewed_by_id');
    }
}

class Issue17AuthenticatableRecord extends Issue17Record implements AuthenticatableContract
{
    use AuthenticatableTrait;
}

class Issue17Owner extends Owner
{
    protected $table = 'owners';

    public function issue17Records(): HasMany
    {
        return $this->hasMany(Issue17Record::class, 'owner_id');
    }
}

class Issue17RecordResource extends AutoResource
{
    protected static ?string $model = Issue17Record::class;

    protected static array $searchableColumns = ['user.name' => 'both'];
}

class Issue17ListRecords extends ListRecords
{
    protected static string $resource = Issue17RecordResource::class;

    public array $issue17Data = [];

    public function setIssue17Table(Table $table): void
    {
        $this->table = $table;
    }
}
