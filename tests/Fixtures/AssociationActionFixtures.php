<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;

class AssociationProduct extends Product
{
    protected $table = 'products';

    protected $attributes = ['price' => '0.00'];

    public function associationOwner(): BelongsTo
    {
        return $this->belongsTo(AssociationOwner::class, 'association_owner_key');
    }

    public function associationSubject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'association_subject_kind', 'association_subject_key');
    }
}

class AssociationOwner extends Owner
{
    protected $table = 'owners';

    public function products(): HasMany
    {
        return $this->hasMany(AssociationProduct::class, 'owner_id');
    }

    public function morphProducts(): MorphMany
    {
        return $this->morphMany(AssociationProduct::class, 'subject');
    }

    public function customProducts(): HasMany
    {
        return $this->hasMany(AssociationProduct::class, 'association_owner_key');
    }

    public function customMorphProducts(): MorphMany
    {
        return $this->morphMany(AssociationProduct::class, 'associationSubject', 'association_subject_kind', 'association_subject_key');
    }

    public function linkedProducts(): BelongsToMany
    {
        return $this->belongsToMany(AssociationProduct::class, 'association_owner_product', 'owner_id', 'product_id');
    }

    public function morphLinkedProducts(): MorphToMany
    {
        return $this->morphToMany(AssociationProduct::class, 'subject', 'association_productables', 'subject_id', 'product_id');
    }
}

class AssociationResource extends AutoResource
{
    protected static ?string $model = AssociationProduct::class;
}

class ReadOnlyAssociationResource extends AssociationResource
{
    protected static bool $readOnly = true;
}
