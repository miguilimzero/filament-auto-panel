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

class Issue11AssociationProduct extends Product
{
    protected $table = 'products';

    protected $attributes = ['price' => '0.00'];

    public function issue11Owner(): BelongsTo
    {
        return $this->belongsTo(Issue11AssociationOwner::class, 'issue11_owner_key');
    }

    public function issue11Subject(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'issue11_subject_kind', 'issue11_subject_key');
    }
}

class Issue11AssociationOwner extends Owner
{
    protected $table = 'owners';

    public function products(): HasMany
    {
        return $this->hasMany(Issue11AssociationProduct::class, 'owner_id');
    }

    public function morphProducts(): MorphMany
    {
        return $this->morphMany(Issue11AssociationProduct::class, 'subject');
    }

    public function customProducts(): HasMany
    {
        return $this->hasMany(Issue11AssociationProduct::class, 'issue11_owner_key');
    }

    public function customMorphProducts(): MorphMany
    {
        return $this->morphMany(Issue11AssociationProduct::class, 'issue11Subject', 'issue11_subject_kind', 'issue11_subject_key');
    }

    public function linkedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Issue11AssociationProduct::class, 'issue11_association_owner_product', 'owner_id', 'product_id');
    }

    public function morphLinkedProducts(): MorphToMany
    {
        return $this->morphToMany(Issue11AssociationProduct::class, 'subject', 'issue11_association_productables', 'subject_id', 'product_id');
    }
}

class Issue11AssociationResource extends AutoResource
{
    protected static ?string $model = Issue11AssociationProduct::class;
}

class Issue11ReadOnlyAssociationResource extends Issue11AssociationResource
{
    protected static bool $readOnly = true;
}
