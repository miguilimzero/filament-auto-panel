<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;
use RuntimeException;

enum FilterGenerationStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft article',
            self::Published => 'Published article',
        };
    }
}

enum FilterGenerationPriority: int
{
    case Low = 1;
    case High = 2;
}

class FilterGenerationOwner extends Owner
{
    protected $table = 'owners';

    public function products(): HasMany
    {
        $relationship = $this->hasMany(Product::class, 'owner_id');
        $relationship->getRelated()->mergeCasts(['connection_label' => 'boolean']);

        return $relationship;
    }

    public function singleProduct(): HasOne
    {
        return $this->hasOne(Product::class, 'owner_id');
    }

    public function morphProducts(): MorphMany
    {
        return $this->morphMany(Product::class, 'subject');
    }

    public function morphProduct(): MorphOne
    {
        return $this->morphOne(Product::class, 'subject');
    }
}

class FilterGenerationCrossConnectionProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        $relationship = parent::owner();
        $relationship->getRelated()->setConnection('secondary');

        return $relationship;
    }
}

class FilterGenerationCustomOwnerKeyProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_id', 'name');
    }
}

class FilterGenerationHiddenTitleOwner extends Owner
{
    protected $table = 'owners';

    protected $hidden = ['name'];
}

class FilterGenerationEncryptedTitleOwner extends Owner
{
    protected $table = 'owners';

    protected $casts = ['name' => 'encrypted'];
}

class FilterGenerationJsonTitleOwner extends Owner
{
    protected $table = 'owners';

    protected $casts = ['name' => 'array'];
}

class FilterGenerationHiddenTitleProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FilterGenerationHiddenTitleOwner::class, 'owner_id');
    }
}

class FilterGenerationEncryptedTitleProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FilterGenerationEncryptedTitleOwner::class, 'owner_id');
    }
}

class FilterGenerationJsonTitleProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(FilterGenerationJsonTitleOwner::class, 'owner_id');
    }
}

class FilterGenerationRuntimeRelationProduct extends Product
{
    protected $table = 'products';

    public ?string $relatedTable = null;

    public ?string $relatedConnection = null;

    public bool $alternateRelatedClass = false;

    public function owner(): BelongsTo
    {
        $relationship = $this->belongsTo(
            $this->alternateRelatedClass ? FilterGenerationOwner::class : Owner::class,
            'owner_id',
        );

        if ($this->relatedTable !== null) {
            $relationship->getRelated()->setTable($this->relatedTable);
        }

        if ($this->relatedConnection !== null) {
            $relationship->getRelated()->setConnection($this->relatedConnection);
        }

        return $relationship;
    }
}

class FilterGenerationRuntimeScopedProduct extends Product
{
    protected $table = 'products';

    public ?string $ownerTenant = null;

    public function owner(): BelongsTo
    {
        $relationship = parent::owner();

        return $this->ownerTenant === null
            ? $relationship
            : $relationship->where('owners.name', $this->ownerTenant);
    }
}

class FilterGenerationBoundScopedProduct extends Product
{
    protected $table = 'products';

    public string $ownerTenant = 'Tenant B';

    public function owner(): BelongsTo
    {
        return parent::owner()->where('owners.name', $this->ownerTenant);
    }
}

class FilterGenerationDeclaredScopedProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return parent::owner()->where('owners.name', 'Tenant A');
    }
}

class FilterGenerationFormHost extends ListProducts
{
    public array $filterGenerationData = [];
}

class FilterGenerationUnrelatedProduct extends Product
{
    protected $table = 'products';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'quantity');
    }

    public function unrelatedRelationship(): HasMany
    {
        throw new RuntimeException('Unrelated relationship methods must not be scanned.');
    }
}

class FilterGenerationResource extends ProductResource
{
    protected static array $visibleColumns = ['name'];

    protected static array $enumDictionary = [
        'status' => ['draft' => ['Draft filter', 'warning'], 'published' => ['Published filter', 'success']],
    ];

    public static function getFilters(): array
    {
        return [Filter::make('manual')->label('Manual filter')];
    }
}

class FilterGenerationReadOnlyResource extends FilterGenerationResource
{
    protected static bool $readOnly = true;
}

class FilterGenerationCustomResource extends FilterGenerationReadOnlyResource
{
    public static function getFilters(): array
    {
        return [
            ...parent::getFilters(),
            QueryBuilder::make()->label(fn (): string => 'Custom query builder')
                ->constraints([TextConstraint::make('customMarker')->attribute('name')]),
        ];
    }
}

class FilterGenerationManager extends AutoRelationManager
{
    protected static string $relationship = 'products';

    protected static bool $associateAttachActions = false;

    protected static array $visibleColumns = ['name'];

    protected static array $enumDictionary = ['status' => ['draft' => ['Draft filter', 'warning']]];

    public function getFilters(): array
    {
        return [Filter::make('manual')];
    }
}

class FilterGenerationReadOnlyManager extends FilterGenerationManager
{
    protected static bool $readOnly = true;
}

class FilterGenerationCustomManager extends FilterGenerationReadOnlyManager
{
    public function getFilters(): array
    {
        return [
            ...parent::getFilters(),
            QueryBuilder::make()->label(fn (): string => 'Custom query builder')
                ->constraints([TextConstraint::make('customMarker')->attribute('name')]),
        ];
    }
}
