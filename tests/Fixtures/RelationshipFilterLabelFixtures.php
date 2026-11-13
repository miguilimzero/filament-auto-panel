<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;

require_once __DIR__ . '/RelationshipOptionLabelFixtures.php';

class RelationshipFilterLabelFixtures
{
    public static function create(): void
    {
        RelationshipOptionLabelFixtures::create();
        RelationshipFilterLabelRecord::$ownerModel = RelationshipLabelUser::class;

        Schema::create('relationship_filter_label_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('owner_id')->nullable();
            $table->string('contact_id')->nullable();
        });
    }

    public static function drop(): void
    {
        Schema::dropIfExists('relationship_filter_label_records');
        RelationshipOptionLabelFixtures::drop();
        RelationshipFilterLabelRecord::$ownerModel = RelationshipLabelUser::class;
    }
}

class RelationshipFilterLabelRecord extends Model
{
    public $timestamps = false;

    protected $table = 'relationship_filter_label_records';

    protected $guarded = [];

    /** @var class-string<Model> */
    public static string $ownerModel = RelationshipLabelUser::class;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(static::$ownerModel, 'owner_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(RelationshipLabelContact::class, 'contact_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RelationshipFilterLabelParent::class, 'parent_id');
    }
}

class RelationshipFilterLabelScopedRecord extends RelationshipFilterLabelRecord
{
    public function owner(): BelongsTo
    {
        return parent::owner()->where('relationship_label_contacts.title', 'Allowed');
    }
}

class RelationshipFilterLabelParent extends Owner
{
    protected $table = 'owners';

    public function records(): HasMany
    {
        return $this->hasMany(RelationshipFilterLabelRecord::class, 'parent_id');
    }
}

class RelationshipFilterLabelResource extends AutoResource
{
    protected static ?string $model = RelationshipFilterLabelRecord::class;

    protected static bool $readOnly = true;
}

class RelationshipFilterLabelFormHost extends ListProducts
{
    public array $relationshipFilterLabelData = [];
}
