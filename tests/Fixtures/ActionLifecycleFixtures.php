<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceListRecords;
use Miguilim\FilamentAutoPanel\Filament\Pages\AutoResourceViewRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;

class ActionLifecycleRecord extends Product
{
    protected $table = 'products';

    protected $fillable = ['name'];

    protected $guarded = ['*'];

    protected $hidden = ['description'];

    protected $attributes = ['price' => '0.00'];
}

class ActionLifecycleOwner extends Owner
{
    protected $table = 'owners';

    public function products(): HasMany
    {
        return $this->hasMany(ActionLifecycleRecord::class, 'owner_id');
    }

    public function linkedProducts(): BelongsToMany
    {
        return $this->belongsToMany(ActionLifecycleRecord::class, 'action_lifecycle_owner_product', 'owner_id', 'product_id')
            ->withPivot('note');
    }
}

class ActionLifecycleManager extends AutoRelationManager
{
    protected static string $relationship = 'products';

    public array $actionData = [];
}

class ActionLifecycleGuardedManager extends ActionLifecycleManager
{
    protected static bool $intrusive = false;
}

class ActionLifecycleResource extends AutoResource
{
    protected static ?string $model = ActionLifecycleRecord::class;

    public static function getListPageActions(): array
    {
        return [AutoCreateAction::make()];
    }

    public static function getViewPageActions(): array
    {
        return [AutoEditAction::make()];
    }

    public static function getHeaderWidgets(): array
    {
        return [];
    }

    public static function getFooterWidgets(): array
    {
        return [];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            TextInput::make('description'),
        ]);
    }
}

class ActionLifecycleGuardedResource extends ActionLifecycleResource
{
    protected static bool $intrusive = false;
}

class ActionLifecycleListPage extends AutoResourceListRecords
{
    protected static string $resource = ActionLifecycleResource::class;

    public array $actionData = [];
}

class ActionLifecycleGuardedListPage extends ActionLifecycleListPage
{
    protected static string $resource = ActionLifecycleGuardedResource::class;
}

class ActionLifecycleViewPage extends AutoResourceViewRecord
{
    protected static string $resource = ActionLifecycleResource::class;

    public function fillForTest(): void
    {
        $this->fillForm();
    }
}

class ActionLifecycleGuardedViewPage extends ActionLifecycleViewPage
{
    protected static string $resource = ActionLifecycleGuardedResource::class;
}
