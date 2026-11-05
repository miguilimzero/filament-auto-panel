<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class RelationshipOptionLabelFixtures
{
    public static function create(string $connection = 'testing'): void
    {
        $schema = Schema::connection($connection);
        $schema->create('relationship_label_contacts', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('handle')->nullable()->unique();
            $table->text('name')->nullable();
            $table->text('title')->nullable();
            $table->text('email')->nullable();
            $table->text('profile_photo_url')->nullable();
            $table->text('portrait_url')->nullable();
        });
        $schema->create('relationship_label_titles', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->text('title')->nullable();
        });
        $schema->create('relationship_label_keys', fn (Blueprint $table) => $table->string('id')->primary());
        $schema->create('relationship_label_records', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_id')->nullable();
        });
        $schema->create('relationship_label_flags', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('contact_id');
            $table->text('name');
            $table->text('email');
            $table->text('profile_photo_url');
            $table->boolean('active')->default(true);
        });
    }

    public static function drop(): void
    {
        foreach (['testing', 'secondary'] as $connection) {
            foreach (['records', 'flags', 'contacts', 'titles', 'keys'] as $table) {
                Schema::connection($connection)->dropIfExists('relationship_label_' . $table);
            }
        }
    }
}

class RelationshipLabelContact extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'relationship_label_contacts';

    protected $guarded = [];
}

class RelationshipLabelTitleContact extends RelationshipLabelContact
{
    protected $table = 'relationship_label_titles';
}

class RelationshipLabelKeyContact extends RelationshipLabelContact
{
    protected $table = 'relationship_label_keys';
}

class RelationshipLabelPlainUser extends User
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'relationship_label_contacts';

    protected $guarded = [];
}

class RelationshipLabelUser extends RelationshipLabelPlainUser implements HasAvatar
{
    public static int $avatarCalls = 0;

    public function getFilamentAvatarUrl(): ?string
    {
        self::$avatarCalls++;

        return '/has-avatar-fallback/' . $this->getKey() . '.svg';
    }
}

class RelationshipLabelKeyUser extends RelationshipLabelUser
{
    protected $table = 'relationship_label_keys';
}

class RelationshipLabelHiddenEmailUser extends RelationshipLabelUser
{
    protected $hidden = ['email'];
}

class RelationshipLabelVisibleEmailUser extends RelationshipLabelUser
{
    protected $visible = ['id', 'name', 'email'];
}

class RelationshipLabelNonvisibleEmailUser extends RelationshipLabelUser
{
    protected $visible = ['id', 'name'];
}

class RelationshipLabelHashedEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => 'hashed'];
}

class RelationshipLabelEncryptedEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => 'encrypted'];
}

class RelationshipLabelEncryptedArrayEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => 'encrypted:array'];
}

class RelationshipLabelEncryptedCollectionEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => AsEncryptedCollection::class];
}

class RelationshipLabelParameterizedEncryptedCollectionEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => AsEncryptedCollection::class . ':' . Collection::class];
}

class RelationshipLabelEncryptedArrayObjectEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => AsEncryptedArrayObject::class];
}

class RelationshipLabelParameterizedEncryptedArrayObjectEmailUser extends RelationshipLabelUser
{
    protected $casts = ['email' => AsEncryptedArrayObject::class . ':' . Collection::class];
}

class RelationshipLabelAccessorUser extends RelationshipLabelUser
{
    public static mixed $emailValue = 'accessor@example.test';

    protected function email(): Attribute
    {
        return Attribute::get(fn (): mixed => self::$emailValue);
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(fn (): string => '/accessor-avatars/' . $this->getKey() . '.svg');
    }

    protected function portraitUrl(): Attribute
    {
        return Attribute::get(fn (): string => '/accessor-portraits/' . $this->getKey() . '.svg');
    }
}

class RelationshipLabelLegacyEmailUser extends RelationshipLabelUser
{
    public function getEmailAttribute(): string
    {
        return 'legacy@example.test';
    }
}

class RelationshipLabelHtmlableUser extends RelationshipLabelUser
{
    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value): HtmlString => new HtmlString($value ?? ''));
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (?string $value): HtmlString => new HtmlString($value ?? ''));
    }
}

class RelationshipLabelNamedUser extends RelationshipLabelUser implements HasName
{
    protected $table = 'relationship_label_titles';

    public function getFilamentName(): string
    {
        return 'Display: ' . $this->title;
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => 'Accessor: ' . $this->title);
    }
}

class RelationshipLabelJoinedUser extends RelationshipLabelUser
{
    protected static function booted(): void
    {
        static::addGlobalScope('flags', static function (Builder $query): void {
            $query->join('relationship_label_flags', 'relationship_label_flags.contact_id', '=', 'relationship_label_contacts.id')
                ->where('relationship_label_flags.active', true);
        });
    }
}

class RelationshipLabelPartialUser extends RelationshipLabelUser
{
    protected static function booted(): void
    {
        static::addGlobalScope('partial', fn (Builder $query) => $query->select(['id', 'name']));
    }
}

class RelationshipLabelRecord extends Model
{
    public $timestamps = false;

    protected $table = 'relationship_label_records';

    protected $guarded = [];

    /** @var class-string<Model> */
    public string $ownerModel = RelationshipLabelContact::class;

    public ?string $ownerKey = null;

    public function owner(): BelongsTo
    {
        return $this->belongsTo($this->ownerModel, 'owner_id', $this->ownerKey);
    }
}
