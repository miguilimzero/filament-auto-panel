<?php

use Filament\Actions\AssociateAction;
use Filament\Actions\AttachAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Support\RelationshipOptionLabel;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelHiddenEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelJoinedUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelParent;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelRecordResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerLabelUserResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationManagerOptionLabelFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;

require_once __DIR__ . '/../Fixtures/RelationManagerOptionLabelFixtures.php';
require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';

beforeEach(fn () => RelationManagerOptionLabelFixtures::create());
afterEach(fn () => RelationManagerOptionLabelFixtures::drop());

dataset('rich label relation managers', ['standalone' => false, 'resource-backed' => true]);
dataset('rich label relationship actions', ['users', 'linkedUsers', 'morphUsers', 'morphLinkedUsers']);

function relationManagerLabelTable(bool $resourceBacked, string $relation): Table
{
    $resource = $relation === 'records' ? RelationManagerLabelRecordResource::class : RelationManagerLabelUserResource::class;
    $class = $resourceBacked
        ? RelationManagerMounter::makeFromResource($resource, $relation)
        : RelationManagerMounter::makeStandalone($relation, visibleColumns: ['name']);
    $manager = new $class();
    $manager->ownerRecord = RelationManagerLabelParent::create(['name' => 'Parent']);
    $manager->pageClass = ListProducts::class;
    $inverse = match ($relation) {
        'linkedUsers' => 'parents',
        'morphUsers' => 'subject',
        'morphLinkedUsers' => 'morphParents',
        default => 'parent',
    };
    $table = Table::make($manager)->relationship(fn () => $manager->getRelationship())->inverseRelationship($inverse);

    return $manager->table($resourceBacked ? $resource::table($table) : $table);
}

function relationManagerLabelAction(Table $table): AttachAction | AssociateAction
{
    return $table->getAction($table->getRelationship() instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany ? 'attach' : 'associate');
}

function relationManagerLabelField(AttachAction | AssociateAction $action): Select
{
    $field = $action->getRecordSelect();
    FilamentSchema::make(new UserFieldListRecords())
        ->statePath('userFieldData')
        ->components([$field])
        ->getFlatComponents(withHidden: true);

    return $field;
}

function relationManagerLabelChoices(AttachAction | AssociateAction $action, string $key, string $search): array
{
    $field = relationManagerLabelField($action->preloadRecordSelect());
    $field->state($key);
    $selected = $field->getOptionLabel();
    expect($field->isHtmlAllowed())->toBeTrue()
        ->and($field->isNative())->toBeFalse()
        ->and($field->getSearchResults($search))->toBe([$key => $selected])
        ->and($field->getOptions())->toBe([$key => $selected]);
    $multiple = relationManagerLabelField($action->multiple());
    $multiple->state([$key]);
    expect($multiple->getOptionLabels())->toBe([$key => $selected])
        ->and($multiple->getState())->toBe([$key]);

    return [$selected, $field, $multiple];
}

it('uses the shared avatar and two-line user label for every native relationship action and selection mode', function (bool $resourceBacked, string $relation): void {
    $user = RelationManagerLabelUser::create([
        'id' => 'user-42', 'name' => 'Alice & Co', 'title' => 'Visible',
        'email' => 'alice@example.test', 'profile_photo_url' => '/avatars/alice.svg',
    ]);
    $table = relationManagerLabelTable($resourceBacked, $relation);
    $action = relationManagerLabelAction($table);
    [$label, $field] = relationManagerLabelChoices($action, $user->getKey(), 'ALICE@EXAMPLE');

    expect($label)->toBe(RelationshipOptionLabel::format($user, 'name', UserColumn::make('name')))
        ->toContain('<b>#user-42</b> - Alice &amp; Co', 'src="/avatars/alice.svg"', 'class="fi-sc-text" style="display:block">alice@example.test')
        ->and($field->getSearchColumns())->toBe(['name', 'id', 'title', 'email'])
        ->and($field->getSearchResults('USER-42'))->toBe(['user-42' => $label]);
})->with('rich label relation managers')->with('rich label relationship actions');

it('honors configured avatar attributes in attach and associate dropdowns', function (bool $resourceBacked, string $relation): void {
    $user = RelationManagerLabelUser::create([
        'id' => 'custom-avatar', 'name' => 'Custom', 'title' => 'Visible',
        'profile_photo_url' => '/default.svg', 'portrait_url' => '/custom.svg',
    ]);
    UserColumn::configureUsing(
        fn (UserColumn $column) => $column->avatarAttribute('portrait_url'),
        during: function () use ($resourceBacked, $relation, $user): void {
            [$label] = relationManagerLabelChoices(relationManagerLabelAction(relationManagerLabelTable($resourceBacked, $relation)), $user->getKey(), 'CUSTOM');
            expect($label)->toContain('src="/custom.svg"')->not->toContain('/default.svg');
        },
    );
})->with('rich label relation managers')->with(['users', 'linkedUsers']);

it('escapes user content and removes unsafe avatars in every relationship action label path', function (string $relation): void {
    $user = RelationManagerLabelUser::create([
        'id' => '<script>key</script>', 'name' => 'Alice <img src=x onerror="alert(1)">',
        'title' => 'Visible', 'email' => '<svg onload="alert(2)">@example.test',
        'profile_photo_url' => 'javascript:alert(3)',
    ]);
    [$label] = relationManagerLabelChoices(relationManagerLabelAction(relationManagerLabelTable(false, $relation)), $user->getKey(), 'ALICE');
    expect($label)->toContain('&lt;script&gt;key&lt;/script&gt;', '&lt;img', '&lt;svg')
        ->not->toContain('<script', '<img', '<svg', 'javascript:')
        ->and(\Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelUser::$avatarCalls)->toBe(0);
})->with(['users', 'linkedUsers']);

it('never exposes hidden email in relationship action labels or searches', function (string $relation): void {
    RelationManagerLabelParent::$userModel = RelationManagerLabelHiddenEmailUser::class;
    $user = RelationManagerLabelHiddenEmailUser::create(['id' => 'private-user', 'name' => 'Private', 'title' => 'Visible', 'email' => 'secret@example.test']);
    [$label, $field] = relationManagerLabelChoices(relationManagerLabelAction(relationManagerLabelTable(false, $relation)), $user->getKey(), 'PRIVATE');
    expect($label)->toBe('<b>#private-user</b> - Private')
        ->and($field->getSearchColumns())->not->toContain('email')
        ->and($field->getSearchResults('secret@example.test'))->toBe([]);
})->with(['users', 'linkedUsers']);

it('retains relationship restrictions for searched preloaded and selected action options', function (string $relation): void {
    RelationManagerLabelUser::create(['id' => 'allowed', 'name' => 'Allowed', 'title' => 'Visible']);
    RelationManagerLabelUser::create(['id' => 'outside', 'name' => 'Outside', 'title' => 'Hidden']);
    $action = relationManagerLabelAction(relationManagerLabelTable(false, $relation))->preloadRecordSelect();
    $field = relationManagerLabelField($action);
    expect($field->getSearchResults('OUTSIDE'))->toBe([])
        ->and($field->getOptions())->toHaveKey('allowed')->not->toHaveKey('outside');
    $field->state('outside');
    expect($field->getOptionLabel(withDefault: false))->toBeNull();
    $multiple = relationManagerLabelField($action->multiple());
    $multiple->state(['allowed', 'outside']);
    expect($multiple->getOptionLabels())->toBe(['allowed' => '<b>#allowed</b> - Allowed']);
})->with('rich label relationship actions');

it('keeps native attach duplicate exclusions when using rich option labels', function (): void {
    $user = RelationManagerLabelUser::create(['id' => 'attached', 'name' => 'Attached', 'title' => 'Visible']);
    $table = relationManagerLabelTable(false, 'linkedUsers');
    $table->getRelationship()->attach($user);
    $field = relationManagerLabelField(relationManagerLabelAction($table)->preloadRecordSelect());
    expect($field->getSearchResults('ATTACHED'))->toBe([])
        ->and($field->getOptions())->toBe([]);
});

it('uses the users own attributes rather than joined table attributes in relationship actions', function (string $relation): void {
    RelationManagerLabelParent::$userModel = RelationManagerLabelJoinedUser::class;
    $user = RelationManagerLabelUser::create([
        'id' => 'joined', 'name' => 'Alice', 'title' => 'Visible',
        'email' => 'alice@example.test', 'profile_photo_url' => '/alice.svg',
    ]);
    DB::table('relationship_label_flags')->insert([
        'id' => 'wrong-key', 'contact_id' => 'joined', 'name' => 'Wrong name',
        'email' => 'wrong@example.test', 'profile_photo_url' => '/wrong.svg', 'active' => true,
    ]);
    [$label] = relationManagerLabelChoices(relationManagerLabelAction(relationManagerLabelTable(false, $relation)), $user->getKey(), 'ALICE@EXAMPLE');
    expect($label)->toBe(RelationshipOptionLabel::format($user, 'name', UserColumn::make('name')))
        ->not->toContain('wrong');
})->with(['users', 'linkedUsers']);

it('also uses the same user dropdown in generated relation manager create and edit forms', function (bool $resourceBacked): void {
    $user = RelationManagerLabelUser::create(['id' => 'form-user', 'name' => 'Alice', 'email' => 'alice@example.test', 'profile_photo_url' => '/alice.svg']);
    $manager = relationManagerLabelTable($resourceBacked, 'records')->getLivewire();
    $form = $manager->form(FilamentSchema::make(new UserFieldListRecords())->model(RelationManagerLabelRecord::class)->statePath('userFieldData'));
    $field = collect($form->getFlatComponents(withHidden: true))->first(fn ($field) => $field instanceof Select && $field->getName() === 'owner_id');
    $field->state($user->getKey());
    $label = RelationshipOptionLabel::format($user, 'name', UserColumn::make('owner.name'));
    expect($field->getOptionLabel())->toBe($label)
        ->and($field->getSearchResults('ALICE@EXAMPLE'))->toBe([$user->getKey() => $label])
        ->and($field->preload()->getOptions())->toBe([$user->getKey() => $label]);
})->with('rich label relation managers');
