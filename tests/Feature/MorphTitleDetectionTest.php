<?php

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;

class MorphTitleTeam extends Model
{
    public $timestamps = false;

    protected $table = 'morph_title_teams';

    protected $guarded = [];
}

class MorphTitleWallet extends Model
{
    public $timestamps = false;

    protected $table = 'morph_title_wallets';

    protected $guarded = [];

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}

class MorphTitleTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'morph_title_transactions';

    protected $guarded = [];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}

class MorphTitleDisplayHost extends ListProducts
{
    public function setMorphTitleTable(Table $table): void
    {
        $this->table = $table;
    }
}

class LegacyMorphTitleGuesser
{
    use CanReadModelSchemas;

    public function title(Model $model, string $relationship): string
    {
        // This is the title-guessing call used by the package before the refactor.
        return $this->guessBelongsToRelationshipTitleColumnName(
            column: $relationship . '_type',
            model: $model->{$relationship}()->getModel()::class,
        );
    }
}

beforeEach(function (): void {
    Schema::create('morph_title_teams', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    Schema::create('morph_title_wallets', function (Blueprint $table): void {
        $table->id();
        $table->string('holder_type');
        $table->unsignedBigInteger('holder_id');
        $table->string('name');
    });
    Schema::create('morph_title_transactions', function (Blueprint $table): void {
        $table->id();
        $table->string('payable_type');
        $table->unsignedBigInteger('payable_id');
        $table->unsignedInteger('amount');
    });
});

afterEach(function (): void {
    Schema::dropIfExists('morph_title_transactions');
    Schema::dropIfExists('morph_title_wallets');
    Schema::dropIfExists('morph_title_teams');
});

function morphTitleRecords(): array
{
    $team = MorphTitleTeam::create(['id' => 71, 'name' => 'The actual Team name']);
    $wallet = new MorphTitleWallet(['name' => 'The wallet name is not the holder name']);
    $wallet->holder()->associate($team);
    $wallet->save();
    $transaction = new MorphTitleTransaction(['amount' => 100]);
    $transaction->payable()->associate($team);
    $transaction->save();

    return [$team, $wallet->fresh(), $transaction->fresh()];
}

it('exposes the referring model as an empty morph placeholder and explains the inconsistent title guesses', function (): void {
    $wallet = new MorphTitleWallet();
    $transaction = new MorphTitleTransaction();
    $legacy = new LegacyMorphTitleGuesser();

    expect($wallet->holder()->getRelated()::class)->toBe(MorphTitleWallet::class)
        ->and($transaction->payable()->getRelated()::class)->toBe(MorphTitleTransaction::class)
        ->and($legacy->title($wallet, 'holder'))->toBe('name')
        ->and($legacy->title($transaction, 'payable'))->toBe('id')
        ->and(RelationshipGuesser::guessMorphTo('holder_type', $wallet))->toBe(['holder', 'name'])
        ->and(RelationshipGuesser::guessMorphTo('payable_type', $transaction))->toBe(['payable', 'id']);

    [$team, $wallet, $transaction] = morphTitleRecords();

    expect($wallet->holder()->getRelated()::class)->toBe(MorphTitleTeam::class)
        ->and($transaction->payable()->getRelated()::class)->toBe(MorphTitleTeam::class)
        ->and($legacy->title($wallet, 'holder'))->toBe('name')
        ->and($legacy->title($transaction, 'payable'))->toBe('name')
        ->and($wallet->holder->is($team))->toBeTrue()
        ->and($transaction->payable->is($team))->toBeTrue();
});

it('reproduces the historical wallet-name versus transaction-id display using the old dotted-state lookup', function (string $surface): void {
    [$team, $wallet, $transaction] = morphTitleRecords();
    $legacy = new LegacyMorphTitleGuesser();

    foreach ([[$wallet, 'holder', $team->name], [$transaction, 'payable', $team->id]] as [$record, $relationship, $expected]) {
        $title = $legacy->title(new ($record::class)(), $relationship);
        $path = $relationship . '.' . $title;

        if ($surface === 'table') {
            $field = TextColumn::make($path);
            $host = new MorphTitleDisplayHost();
            $host->setMorphTitleTable(Table::make($host)->query($record->newQuery())->columns([$field]));
            $field->record($record);
        } else {
            $field = TextEntry::make($path);
            FilamentSchema::make(new MorphTitleDisplayHost())->model($record)->components([$field])->getFlatComponents(withHidden: true);
        }

        expect($field->getState())->toBe($expected);
    }
})->with(['table', 'infolist']);

it('renders the same actual Team name for both current generators despite differing source-model schemas', function (string $surface, bool $eager): void {
    [$team, $wallet, $transaction] = morphTitleRecords();

    foreach ([[$wallet, 'holder', 'name'], [$transaction, 'payable', 'id']] as [$record, $relationship, $guessedTitle]) {
        $record = $eager ? $record->load($relationship) : $record->fresh();
        expect($record->relationLoaded($relationship))->toBe($eager);
        $path = $relationship . '.' . $guessedTitle;

        if ($surface === 'table') {
            $columns = TableGenerator::make($record::class);
            $field = $columns[$relationship];
            $host = new MorphTitleDisplayHost();
            $host->setMorphTitleTable(Table::make($host)->query($record->newQuery())->columns($columns));
            $field->record($record);
        } else {
            $schema = FilamentSchema::make(new MorphTitleDisplayHost())->model($record)->components(InfolistGenerator::make($record::class));
            $field = collect($schema->getFlatComponents(withHidden: true))
                ->first(fn ($component): bool => $component instanceof TextEntry && $component->getName() === $path);
        }

        expect($field->getName())->toBe($path)
            ->and($field->getState())->toBe($team->name)
            ->and(html_entity_decode(strip_tags($field->toHtml()), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toContain($team->name);
    }
})->with(['table', 'infolist'])->with(['lazy-loaded' => false, 'eager-loaded' => true]);
