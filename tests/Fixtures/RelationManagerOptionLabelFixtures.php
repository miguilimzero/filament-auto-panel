<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;

require_once __DIR__ . '/RelationshipOptionLabelFixtures.php';

class RelationManagerOptionLabelFixtures
{
    public static function create(): void
    {
        RelationshipOptionLabelFixtures::create();
        Schema::table('relationship_label_contacts', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable();
            $table->nullableMorphs('subject');
        });
        Schema::table('relationship_label_records', fn (Blueprint $table) => $table->foreignId('parent_id')->nullable());
        Schema::create('relationship_label_parent_user', function (Blueprint $table): void {
            $table->foreignId('parent_id');
            $table->string('user_id');
        });
        Schema::create('relationship_label_userables', function (Blueprint $table): void {
            $table->morphs('subject');
            $table->string('user_id');
        });
        RelationManagerLabelParent::$userModel = RelationManagerLabelUser::class;
        RelationshipLabelUser::$avatarCalls = 0;
    }

    public static function drop(): void
    {
        Schema::dropIfExists('relationship_label_userables');
        Schema::dropIfExists('relationship_label_parent_user');
        RelationshipOptionLabelFixtures::drop();
    }
}

class RelationManagerLabelParent extends Owner
{
    protected $table = 'owners';

    public static string $userModel = RelationManagerLabelUser::class;

    public function users(): HasMany
    {
        return $this->hasMany(static::$userModel, 'parent_id')->where('title', 'Visible');
    }

    public function linkedUsers(): BelongsToMany
    {
        return $this->belongsToMany(static::$userModel, 'relationship_label_parent_user', 'parent_id', 'user_id')->where('title', 'Visible');
    }

    public function morphUsers(): MorphMany
    {
        return $this->morphMany(static::$userModel, 'subject')->where('title', 'Visible');
    }

    public function morphLinkedUsers(): MorphToMany
    {
        return $this->morphToMany(static::$userModel, 'subject', 'relationship_label_userables', 'subject_id', 'user_id')->where('title', 'Visible');
    }

    public function records(): HasMany
    {
        return $this->hasMany(RelationManagerLabelRecord::class, 'parent_id');
    }
}

class RelationManagerLabelUser extends RelationshipLabelUser
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RelationManagerLabelParent::class, 'parent_id');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(RelationManagerLabelParent::class, 'relationship_label_parent_user', 'user_id', 'parent_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function morphParents(): MorphToMany
    {
        return $this->morphedByMany(RelationManagerLabelParent::class, 'subject', 'relationship_label_userables', 'user_id', 'subject_id');
    }
}

class RelationManagerLabelHiddenEmailUser extends RelationManagerLabelUser
{
    protected $hidden = ['email'];
}

class RelationManagerLabelJoinedUser extends RelationManagerLabelUser
{
    protected static function booted(): void
    {
        static::addGlobalScope('flags', static function (Builder $query): void {
            $query->join('relationship_label_flags', 'relationship_label_flags.contact_id', '=', 'relationship_label_contacts.id')
                ->where('relationship_label_flags.active', true);
        });
    }
}

class RelationManagerLabelRecord extends RelationshipLabelRecord
{
    public string $ownerModel = RelationManagerLabelUser::class;
}

class RelationManagerLabelUserResource extends AutoResource
{
    protected static ?string $model = RelationManagerLabelUser::class;
}

class RelationManagerLabelRecordResource extends AutoResource
{
    protected static ?string $model = RelationManagerLabelRecord::class;
}
