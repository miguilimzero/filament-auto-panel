<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;

class MorphFormFixtures
{
    private static ?Panel $previousPanel = null;

    private static array $previousMorphMap = [];

    private static bool $previousRequiresMorphMap = false;

    public static function create(): void
    {
        static::$previousPanel = Filament::getCurrentPanel();
        static::$previousMorphMap = Relation::morphMap();
        static::$previousRequiresMorphMap = Relation::requiresMorphMap();
        Relation::requireMorphMap(false);
        Relation::morphMap([], merge: false);
        static::createTargets();
        static::createRecords();
    }

    public static function createTargets(string $connection = 'testing'): void
    {
        $schema = Schema::connection($connection);
        $schema->create('morph_form_names', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('notes')->nullable();
            $table->string('external_key')->nullable()->unique();
            $table->boolean('active')->default(true);
        });
        $schema->create('morph_form_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
        });
        $schema->create('morph_form_keys', function (Blueprint $table): void {
            $table->string('target_key')->primary();
            $table->unsignedBigInteger('id')->nullable();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('email')->nullable();
        });
        $schema->create('morph_form_integer_keys', function (Blueprint $table): void {
            $table->unsignedBigInteger('target_number')->primary();
        });
    }

    public static function createJoinedTargets(string $connection): void
    {
        Schema::connection($connection)->create('morph_form_target_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('target_id');
            $table->string('name');
            $table->string('title');
        });
    }

    public static function createRecords(
        bool $stringKey = false,
        bool $typeNullable = true,
        bool $idNullable = true,
        bool $reverse = false,
        bool $multiple = false,
        string $connection = 'testing',
    ): void {
        $schema = Schema::connection($connection);
        $schema->dropIfExists('morph_form_records');
        $schema->create('morph_form_records', function (Blueprint $table) use ($stringKey, $typeNullable, $idNullable, $reverse, $multiple): void {
            $table->id();
            $table->string('name')->nullable();
            foreach ($multiple ? ['subject', 'review_target'] : ['subject'] as $prefix) {
                $columns = $reverse ? ['id', 'type'] : ['type', 'id'];
                foreach ($columns as $suffix) {
                    $column = ($suffix === 'type' || $stringKey)
                        ? $table->string("{$prefix}_{$suffix}")
                        : $table->unsignedBigInteger("{$prefix}_id");
                    $column->nullable($suffix === 'type' ? $typeNullable : $idNullable);
                }
            }
        });
    }

    public static function panel(array $resources = [MorphFormNameResource::class]): Panel
    {
        $panel = Panel::make()->id('morph-forms')->path('morph-forms')->resources($resources);
        Filament::setCurrentPanel($panel);

        return $panel;
    }

    public static function drop(): void
    {
        try {
            foreach (['testing', 'secondary'] as $connection) {
                foreach (['morph_form_records', 'morph_form_target_details', 'morph_form_names', 'morph_form_titles', 'morph_form_keys', 'morph_form_integer_keys'] as $table) {
                    Schema::connection($connection)->dropIfExists($table);
                }
            }
        } finally {
            Relation::morphMap(static::$previousMorphMap, merge: false);
            Relation::requireMorphMap(static::$previousRequiresMorphMap);
            Filament::setCurrentPanel(static::$previousPanel);
        }
    }
}

class MorphFormRawRecord extends Model
{
    public $timestamps = false;

    protected $table = 'morph_form_records';

    protected $guarded = [];
}

class MorphFormRecord extends MorphFormRawRecord
{
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewTarget(): MorphTo
    {
        return $this->morphTo();
    }
}

class MorphFormOwner extends Owner
{
    protected $table = 'owners';

    public function morphFormRecords(): HasMany
    {
        return $this->hasMany(MorphFormRecord::class, 'owner_id');
    }
}

class MorphFormCustomOwnerRecord extends MorphFormRecord
{
    public function subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id', 'external_key');
    }
}

class MorphFormUnverifiedRecord extends MorphFormRawRecord
{
    public function subject(): HasMany
    {
        return $this->hasMany(MorphFormNameTarget::class, 'id');
    }

    public function reviewTarget(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'different_type', 'different_id');
    }
}

class MorphFormNameTarget extends Model
{
    public $timestamps = false;

    protected $table = 'morph_form_names';

    protected $guarded = [];
}

class MorphFormStringIdTarget extends MorphFormNameTarget
{
    protected $keyType = 'string';
}

class MorphFormTitleTarget extends MorphFormNameTarget
{
    protected $table = 'morph_form_titles';
}

class MorphFormKeyTarget extends MorphFormNameTarget
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'target_key';

    protected $table = 'morph_form_keys';
}

class MorphFormIntegerKeyTarget extends MorphFormNameTarget
{
    public $incrementing = false;

    protected $primaryKey = 'target_number';

    protected $table = 'morph_form_integer_keys';
}

class MorphFormSecondaryTarget extends MorphFormNameTarget
{
    protected $connection = 'secondary';
}

class MorphFormDeniedTarget extends MorphFormNameTarget {}

class MorphFormMapOnlyTarget extends MorphFormNameTarget {}

class MorphFormScopedTarget extends MorphFormNameTarget
{
    protected static function booted(): void
    {
        static::addGlobalScope('active', fn (Builder $query) => $query->where('active', true));
    }
}

class MorphFormJoinedTarget extends MorphFormNameTarget
{
    protected static function booted(): void
    {
        static::addGlobalScope('details', fn (Builder $query) => $query->join(
            'morph_form_target_details',
            'morph_form_target_details.target_id',
            '=',
            $query->getModel()->qualifyColumn('id'),
        ));
    }
}

class MorphFormSafeTarget extends MorphFormNameTarget
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setHidden(config('morph-form.hidden', []));
        $this->setVisible(config('morph-form.visible', []));
        $this->mergeCasts(config('morph-form.casts', []));
    }
}

class MorphFormAccessorTarget extends MorphFormNameTarget
{
    protected function email(): Attribute
    {
        return Attribute::get(fn (): string => 'accessor-only-needle');
    }
}

class MorphFormNameResource extends Resource
{
    protected static ?string $model = MorphFormNameTarget::class;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        throw new LogicException('Morph options must query the concrete model, not the resource.');
    }
}

class MorphFormDuplicateNameResource extends MorphFormNameResource {}

class MorphFormDeniedNameResource extends MorphFormNameResource
{
    public static function canAccess(): bool
    {
        return false;
    }
}

class MorphFormStringIdResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormStringIdTarget::class;
}

class MorphFormTitleResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormTitleTarget::class;
}

class MorphFormKeyResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormKeyTarget::class;
}

class MorphFormIntegerKeyResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormIntegerKeyTarget::class;
}

class MorphFormSecondaryResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormSecondaryTarget::class;
}

class MorphFormDeniedResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormDeniedTarget::class;

    public static function canAccess(): bool
    {
        return false;
    }
}

class MorphFormScopedResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormScopedTarget::class;
}

class MorphFormJoinedResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormJoinedTarget::class;
}

class MorphFormSafeResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormSafeTarget::class;
}

class MorphFormAccessorResource extends MorphFormNameResource
{
    protected static ?string $model = MorphFormAccessorTarget::class;
}

class MorphFormRecordResource extends AutoResource
{
    protected static ?string $model = MorphFormRecord::class;

    public static function canAccess(): bool
    {
        return true;
    }
}

class MorphFormHost extends ListRecords
{
    protected static string $resource = MorphFormRecordResource::class;

    public array $morphFormData = [];

    public function setMorphFormTable(Table $table): void
    {
        $this->table = $table;
    }
}
