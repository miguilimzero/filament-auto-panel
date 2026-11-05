<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Support\Components\ViewComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

class RelationshipSearchFixtures
{
    public static function create(string $connection = 'testing'): void
    {
        $schema = Schema::connection($connection);
        $schema->create('relationship_search_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('title')->nullable();
            $table->string('notes')->nullable();
            $table->string('handle')->nullable()->unique();
        });
        $schema->create('relationship_search_records', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_id')->nullable();
        });
    }

    public static function createContactFlags(string $connection): void
    {
        Schema::connection($connection)->create('relationship_search_contact_flags', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->boolean('active')->default(true);
            $table->string('name')->default('Join-only name');
            $table->string('title')->default('Join-only title');
            $table->string('email')->default('join-only@example.test');
        });
    }

    public static function drop(): void
    {
        foreach (['testing', 'secondary'] as $connection) {
            $schema = Schema::connection($connection);
            $schema->dropIfExists('relationship_search_records');
            $schema->dropIfExists('relationship_search_contact_flags');
            $schema->dropIfExists('relationship_search_contacts');
            $schema->dropIfExists('relationship_search_key_contacts');
        }
    }
}

class RelationshipSearchContact extends Model
{
    public $timestamps = false;

    protected $table = 'relationship_search_contacts';

    protected $guarded = [];
}

class RelationshipSearchJoinedContact extends RelationshipSearchContact
{
    protected static function booted(): void
    {
        static::addGlobalScope('contact_flags', static function (Builder $query): void {
            $table = $query->getModel()->getTable();
            $query->join('relationship_search_contact_flags', 'relationship_search_contact_flags.contact_id', '=', "{$table}.id")
                ->where('relationship_search_contact_flags.active', true)
                ->select("{$table}.*");
        });
    }
}

class RelationshipSearchKeyContact extends RelationshipSearchContact
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'contact_key';

    protected $table = 'relationship_search_key_contacts';
}

class RelationshipSearchSecondaryContact extends RelationshipSearchContact
{
    protected $connection = 'secondary';
}

class RelationshipSearchAccessorContact extends RelationshipSearchContact
{
    protected $primaryKey = 'virtual_key';

    protected function virtualKey(): Attribute
    {
        return Attribute::get(fn (): string => 'accessor-key-needle');
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (): string => 'accessor-email-needle');
    }
}

class RelationshipSearchRecord extends Model
{
    public $timestamps = false;

    protected $table = 'relationship_search_records';

    protected $guarded = [];

    /** @var class-string<Model> */
    public string $ownerModel = RelationshipSearchContact::class;

    public ?string $ownerKey = null;

    public array $ownerHidden = [];

    public array $ownerVisible = [];

    public array $ownerCasts = [];

    public function owner(): BelongsTo
    {
        $relationship = $this->belongsTo($this->ownerModel, 'owner_id', $this->ownerKey);
        $relationship->getRelated()
            ->setHidden($this->ownerHidden)
            ->setVisible($this->ownerVisible)
            ->mergeCasts($this->ownerCasts);

        return $relationship;
    }
}

class CustomRelationshipSearchFormGenerator extends FormGenerator
{
    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return parent::handleRelationshipColumn($column, $relationshipName, $relationshipTitleColumnName)
            ->searchable(['handle'])
            ->label('Extension owner');
    }
}
