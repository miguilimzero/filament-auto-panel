<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;

class SecretAccount extends Model
{
    public $timestamps = false;

    protected $table = 'secret_accounts';

    protected $guarded = [];

    protected $hidden = ['password', 'margin', 'internal_note'];

    protected $casts = [
        'password' => 'hashed',
        'api_token' => 'encrypted',
        'settings' => 'encrypted:array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(SecretOwner::class, 'owner_id');
    }
}

class SecretOwner extends Owner
{
    protected $table = 'owners';

    public function secretAccounts(): HasMany
    {
        return $this->hasMany(SecretAccount::class, 'owner_id');
    }
}

class SecretOwnerResource extends AutoResource
{
    protected static ?string $model = SecretOwner::class;
}

class SecretAccountResource extends AutoResource
{
    protected static ?string $model = SecretAccount::class;

    protected static array $excludedColumns = ['legacy_key'];
}

class SecretAccountGuardedResource extends SecretAccountResource
{
    protected static bool $intrusive = false;
}

class SecretCustomFormResource extends SecretAccountResource
{
    protected static array $excludedColumns = [];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            TextInput::make('margin'),
        ]);
    }
}

class SecretCustomFormGuardedResource extends SecretCustomFormResource
{
    protected static bool $intrusive = false;
}

class SecretSearchableTitleResource extends SecretAccountResource
{
    protected static ?string $recordTitleAttribute = 'legacy_key';
}

class SecretEmailExcludedResource extends SecretAccountResource
{
    protected static array $excludedColumns = ['email', 'name'];
}
