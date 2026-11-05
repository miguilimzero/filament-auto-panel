<?php

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldAccessorIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldAuthenticatableRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldNamedIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldNonUserRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldOrphanRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldRecordResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldTitleIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldUnverifiedRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Zvizvi\UserFields\Components\UserColumn;
use Zvizvi\UserFields\Components\UserEntry;
use Zvizvi\UserFields\Components\UserSelect;

require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';

beforeEach(function (): void {
    UserFieldFixtures::create();
});

afterEach(function (): void {
    UserFieldFixtures::drop();
});

function userFieldEntries(array $components, Model|string $model = UserFieldRecord::class): array
{
    $schema = FilamentSchema::make(new UserFieldListRecords())->model($model)->components($components);

    return collect($schema->getFlatComponents(withHidden: true))
        ->filter(fn ($component): bool => $component instanceof TextEntry)
        ->keyBy(fn ($entry): string => $entry->getName())
        ->all();
}

function mountUserFieldColumn(TextColumn $column, Model $record): TextColumn
{
    $host = new UserFieldListRecords();
    $table = Table::make($host)->query($record->newQuery())->columns([$column]);
    $host->setUserFieldTable($table);
    $table->getColumns();

    return $column->record($record);
}

function assertUserFieldHtml(string $html, UserFieldIdentity $identity, string $name): void
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($text)->toContain($name)
        ->and($html)->toContain('<img', $identity->getFilamentAvatarUrl())
        ->and(strtolower($html))->not->toContain('<script', 'ui-avatars.com');
}

it('loads the mandatory real user-fields components and registered avatar view', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Plugin <b>identity</b> & Co']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $field = UserSelect::make('author_id')->relationship('author', 'name')->searchable();
    FilamentSchema::make(new UserFieldListRecords())->model($record)->components([$field])->getFlatComponents(withHidden: true);

    expect(\Composer\InstalledVersions::isInstalled('zvizvi/user-fields'))->toBeTrue()
        ->and(view()->exists('user-fields::user-avatar-option'))->toBeTrue();
    assertUserFieldHtml((string) $field->getSearchResults('Plugin')[$identity->id], $identity, $identity->name);
    assertUserFieldHtml(mountUserFieldColumn(UserColumn::make('author.name'), $record)->toHtml(), $identity, $identity->name);
    $entry = userFieldEntries([UserEntry::make('author.name')], $record)['author.name'];
    assertUserFieldHtml($entry->toHtml(), $identity, $identity->name);
});

it('maps authenticatable foreign keys to real user fields using the actual relationship', function (string $foreignKey, string $relationship, string $label): void {
    $fields = FormGenerator::make(UserFieldRecord::class, relationManagerView: true);
    FilamentSchema::make(new UserFieldListRecords())->model(UserFieldRecord::class)->components($fields)->getFlatComponents(withHidden: true);
    $columns = TableGenerator::make(UserFieldRecord::class);
    $entries = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class));

    expect(RelationshipGuesser::guessBelongsTo($foreignKey, new UserFieldRecord()))->toBe([$relationship, 'name'])
        ->and($fields[$foreignKey])->toBeInstanceOf(UserSelect::class)
        ->and($fields[$foreignKey]->getName())->toBe($foreignKey)
        ->and($fields[$foreignKey]->getRelationshipName())->toBe($relationship)
        ->and($fields[$foreignKey]->getRelationshipTitleAttribute())->toBe('name')
        ->and($fields[$foreignKey]->getLabel())->toBe($label)
        ->and($columns[$foreignKey])->toBeInstanceOf(UserColumn::class)
        ->and($columns[$foreignKey]->getName())->toBe("{$relationship}.name")
        ->and($columns[$foreignKey]->getLabel())->toBe($label)
        ->and($entries["{$relationship}.name"])->toBeInstanceOf(UserEntry::class)
        ->and($entries["{$relationship}.name"]->getLabel())->toBe($label);
})->with([
    'conventional user' => ['user_id', 'user', 'User'],
    'author alias' => ['author_id', 'author', 'Author'],
    'camel case relationship' => ['reviewed_by_id', 'reviewedBy', 'Reviewed by'],
]);

it('preserves nullability searchable and case-insensitive relationship select configuration', function (): void {
    $fields = FormGenerator::make(UserFieldRecord::class, relationManagerView: true);

    foreach (['user_id' => true, 'author_id' => false] as $name => $required) {
        expect($fields[$name])->toBeInstanceOf(UserSelect::class)
            ->and($fields[$name]->isRequired())->toBe($required)
            ->and($fields[$name]->isSearchable())->toBeTrue()
            ->and($fields[$name]->isSearchForcedCaseInsensitive())->toBeTrue();
    }
});

it('searches real user options case-insensitively and renders escaped names with local avatars', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Alice <script>alert("user-field")</script> & Co']);
    UserFieldIdentity::create(['name' => 'Unrelated person']);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['author_id'];
    FilamentSchema::make(new UserFieldListRecords())->model(UserFieldRecord::class)->components([$field])->getFlatComponents(withHidden: true);

    expect($field)->toBeInstanceOf(UserSelect::class);
    $results = $field->getSearchResults('aLiCe');

    expect(array_keys($results))->toBe([$identity->getKey()])
        ->and($field->isHtmlAllowed())->toBeTrue();
    assertUserFieldHtml((string) $results[$identity->getKey()], $identity, $identity->name);
});

it('hydrates a selected user option through the real relationship label renderer', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Selected <b>user</b> & Co']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['author_id'];
    $host = new UserFieldListRecords();
    $schema = FilamentSchema::make($host)->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill($record->attributesToArray());

    expect($field)->toBeInstanceOf(UserSelect::class)
        ->and((int) $host->userFieldData['author_id'])->toBe($identity->id);
    assertUserFieldHtml((string) $field->getOptionLabel(), $identity, $identity->name);
});

it('saves generated resource user selects using foreign keys rather than relationship display paths', function (string $operation): void {
    $first = UserFieldIdentity::create(['name' => 'First identity']);
    $second = UserFieldIdentity::create(['name' => 'Second identity']);
    $record = $operation === 'edit'
        ? UserFieldRecord::create(['user_id' => $first->id, 'author_id' => $first->id])->fresh()
        : new UserFieldRecord();
    $host = new UserFieldListRecords();
    $schema = UserFieldRecordResource::form(FilamentSchema::make($host)->model($record)->operation($operation)->statePath('userFieldData'));
    $edit = $operation === 'edit' ? AutoEditAction::make()->livewire($host)->record($record) : null;
    if ($edit) {
        $edit->mount(['schema' => $schema]);
    } else {
        $schema->fill($record->attributesToArray());
    }
    $fields = collect($schema->getFlatComponents(withHidden: true))
        ->filter(fn ($component): bool => $component instanceof Select)
        ->keyBy(fn ($field): string => $field->getName());

    expect($fields['user_id'])->toBeInstanceOf(UserSelect::class);
    $host->userFieldData['user_id'] = $second->id;
    $host->userFieldData['author_id'] = null;
    $state = $edit
        ? $schema->getState(afterValidate: fn (array $state) => $edit->data($state))
        : $schema->getState();

    expect($state)->toHaveKeys(['user_id', 'author_id'])->not->toHaveKeys(['user', 'user.name', 'author.name'])
        ->and($state['author_id'])->toBeNull();
    if ($edit) {
        $edit->call(['schema' => $schema]);
    } else {
        $record->fill($state)->save();
        $schema->model($record)->saveRelationships();
    }

    expect((int) $record->refresh()->user_id)->toBe($second->id)
        ->and($record->author_id)->toBeNull()
        ->and($record->user->is($second))->toBeTrue();
})->with(['create', 'edit']);


it('validates a required user select before saving an empty foreign key', function (): void {
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['user_id'];
    $schema = FilamentSchema::make(new UserFieldListRecords())->model(UserFieldRecord::class)->statePath('userFieldData')->components([$field]);
    $schema->fill(['user_id' => null]);

    expect($field)->toBeInstanceOf(UserSelect::class)
        ->and(fn () => $schema->getState())->toThrow(ValidationException::class)
        ->and(UserFieldRecord::count())->toBe(0);
});

it('renders real user display HTML with escaped names and local avatars', function (string $surface): void {
    $identity = UserFieldIdentity::create(['name' => 'Display <script>alert("user-field")</script> & Co']);
    $differentUser = UserFieldIdentity::create(['name' => 'Not the author']);
    $record = UserFieldRecord::create(['user_id' => $differentUser->id, 'author_id' => $identity->id]);

    if ($surface === 'table') {
        $component = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['author_id'], $record);
        expect($component)->toBeInstanceOf(UserColumn::class);
    } else {
        $component = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)['author.name'];
        expect($component)->toBeInstanceOf(UserEntry::class);
    }

    assertUserFieldHtml($component->toHtml(), $identity, $identity->name);
})->with(['table', 'infolist']);

it('retains native related-name search sorting and eager loading for user columns', function (): void {
    $zulu = UserFieldIdentity::create(['name' => 'Zulu']);
    $alpha = UserFieldIdentity::create(['name' => 'Alpha']);
    $last = UserFieldRecord::create(['user_id' => $zulu->id]);
    $first = UserFieldRecord::create(['user_id' => $alpha->id]);
    $column = TableGenerator::make(UserFieldRecord::class, searchableColumns: ['user.name' => 'both'])['user_id'];
    mountUserFieldColumn($column, $last);

    expect($column)->toBeInstanceOf(UserColumn::class)
        ->and($column->getName())->toBe('user.name')
        ->and($column->isGloballySearchable())->toBeTrue()
        ->and($column->isIndividuallySearchable())->toBeTrue();
    $isFirst = true;
    expect($column->applySearchConstraint(UserFieldRecord::query(), 'Alpha', $isFirst)->pluck('id')->all())->toBe([$first->id]);
    $column->sortable();
    expect($column->applySort(UserFieldRecord::query(), 'asc')->pluck('id')->all())->toBe([$first->id, $last->id])
        ->and($column->applySort(UserFieldRecord::query(), 'desc')->pluck('id')->all())->toBe([$last->id, $first->id]);

    $connection = $last->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    try {
        $records = $column->applyEagerLoading(UserFieldRecord::query())->get();
        $queryCount = count($connection->getQueryLog());

        expect($queryCount)->toBe(2);
        foreach ($records as $record) {
            expect($record->relationLoaded('user'))->toBeTrue();
            expect($column->record($record)->toHtml())->toContain($record->user->name, $record->user->getFilamentAvatarUrl());
        }
        expect(count($connection->getQueryLog()))->toBe($queryCount);
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
});

it('keeps non-authenticatable relationships generic even when the relationship is named user', function (): void {
    foreach ([UserFieldNonUserRecord::class => 'user_id', UserFieldRecord::class => 'owner_id'] as $model => $foreignKey) {
        $relationship = $foreignKey === 'user_id' ? 'user' : 'owner';
        $field = FormGenerator::make($model, relationManagerView: true)[$foreignKey];
        $column = TableGenerator::make($model)[$foreignKey];
        $entry = userFieldEntries(InfolistGenerator::make($model), $model)["{$relationship}.name"];

        expect($field::class)->toBe(Select::class)
            ->and($field->getRelationshipName())->toBe($relationship)
            ->and($column::class)->toBe(TextColumn::class)
            ->and($column->getName())->toBe("{$relationship}.name")
            ->and($entry::class)->toBe(TextEntry::class);
    }
});

it('leaves orphan user IDs and unverified relationships as ordinary numeric fields', function (): void {
    foreach ([UserFieldOrphanRecord::class, UserFieldUnverifiedRecord::class] as $model) {
        $fields = FormGenerator::make($model, relationManagerView: true);
        $columns = TableGenerator::make($model);
        $entries = userFieldEntries(InfolistGenerator::make($model), $model);

        foreach (['user_id', 'author_id'] as $foreignKey) {
            expect(RelationshipGuesser::guessBelongsTo($foreignKey, new $model()))->toBe([])
                ->and($fields[$foreignKey]::class)->toBe(TextInput::class)
                ->and($fields[$foreignKey]->isNumeric())->toBeTrue()
                ->and($columns[$foreignKey]::class)->toBe(TextColumn::class)
                ->and($columns[$foreignKey]->getName())->toBe($foreignKey)
                ->and($entries[$foreignKey]::class)->toBe(TextEntry::class);
        }
    }
});

it('falls back to generic title fields for authenticatable models without a Filament-compatible name', function (): void {
    $identity = UserFieldTitleIdentity::create(['title' => 'Title only']);
    $user = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $user->id, 'title_user_id' => $identity->id]);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['title_user_id'];
    FilamentSchema::make(new UserFieldListRecords())->model($record)->components([$field])->getFlatComponents(withHidden: true);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['title_user_id'], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)['titleUser.title'];

    expect($field::class)->toBe(Select::class)
        ->and($field->getRelationshipTitleAttribute())->toBe('title')
        ->and($field->getSearchResults('Title'))->toBe([$identity->id => 'Title only'])
        ->and($column::class)->toBe(TextColumn::class)
        ->and($entry::class)->toBe(TextEntry::class)
        ->and($column->toHtml())->toContain('Title only')->not->toContain('<img')
        ->and($entry->toHtml())->toContain('Title only')->not->toContain('<img');
});

it('supports title-backed authenticatable models with a custom Filament name or name accessor', function (string $identityClass, string $foreignKey, string $relationship, string $prefix): void {
    $identity = $identityClass::create(['title' => 'Title <b>identity</b> & Co']);
    $required = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $required->id, $foreignKey => $identity->id]);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)[$foreignKey];
    $schema = FilamentSchema::make(new UserFieldListRecords())->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill($record->attributesToArray());
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)[$foreignKey], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)["{$relationship}.title"];

    expect($field)->toBeInstanceOf(UserSelect::class)
        ->and($field->getRelationshipTitleAttribute())->toBe('title')
        ->and($column)->toBeInstanceOf(UserColumn::class)
        ->and($column->getName())->toBe("{$relationship}.title")
        ->and($entry)->toBeInstanceOf(UserEntry::class);
    $results = $field->getSearchResults('Title');
    $displayName = $prefix . $identity->title;
    assertUserFieldHtml((string) $results[$identity->id], $identity, $displayName);
    assertUserFieldHtml((string) $field->getOptionLabel(), $identity, $displayName);
    assertUserFieldHtml($column->toHtml(), $identity, $displayName);
    assertUserFieldHtml($entry->toHtml(), $identity, $displayName);
})->with([
    'HasName contract' => [UserFieldNamedIdentity::class, 'named_user_id', 'namedUser', 'Display: '],
    'name accessor' => [UserFieldAccessorIdentity::class, 'accessor_user_id', 'accessorUser', 'Accessor: '],
]);

it('renders nullable or missing related users without an avatar or a type error', function (?int $authorId): void {
    $identity = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $authorId]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['author_id'], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)['author.name'];

    expect($column)->toBeInstanceOf(UserColumn::class)
        ->and($entry)->toBeInstanceOf(UserEntry::class)
        ->and(blank($column->getState()))->toBeTrue()
        ->and(blank($entry->getState()))->toBeTrue()
        ->and($column->toHtml())->not->toContain('<img', 'ui-avatars.com')
        ->and($entry->toHtml())->not->toContain('<img', 'ui-avatars.com');
})->with(['nullable foreign key' => [null], 'missing related row' => [999999]]);

it('preserves exclusion overwrite and enum dictionary priority ahead of user detection', function (): void {
    $formOverride = TextInput::make('author_id')->label('Explicit author');
    $tableOverride = TextColumn::make('author_id')->label('Explicit author');
    $entryOverride = TextEntry::make('author_id')->label('Explicit author');
    $dictionary = [
        'user_id' => [1 => 'Excluded'],
        'author_id' => [1 => 'Must not replace overwrite'],
        'reviewed_by_id' => [1 => ['Reviewer dictionary', 'success']],
    ];
    $fields = FormGenerator::make(UserFieldRecord::class, exceptColumns: ['user_id'], overwriteColumns: ['user_id' => TextInput::make('user_id'), 'author_id' => $formOverride], enumDictionary: $dictionary, relationManagerView: true);
    $columns = TableGenerator::make(UserFieldRecord::class, exceptColumns: ['user_id'], overwriteColumns: ['user_id' => TextColumn::make('user_id'), 'author_id' => $tableOverride], enumDictionary: $dictionary);
    $entries = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class, exceptColumns: ['user_id'], overwriteColumns: ['user_id' => TextEntry::make('user_id'), 'author_id' => $entryOverride], enumDictionary: $dictionary));
    $record = new UserFieldRecord(['reviewed_by_id' => 1]);
    $dictionaryColumn = mountUserFieldColumn($columns['reviewed_by_id'], $record);

    expect($fields)->not->toHaveKey('user_id')
        ->and($columns)->not->toHaveKey('user_id')
        ->and($entries)->not->toHaveKeys(['user_id', 'user.name'])
        ->and($fields['author_id'])->toBe($formOverride)
        ->and($columns['author_id'])->toBe($tableOverride)
        ->and($entries['author_id'])->toBe($entryOverride)
        ->and($fields['reviewed_by_id']::class)->toBe(Select::class)
        ->and($fields['reviewed_by_id']->getOptions())->toBe([1 => 'Reviewer dictionary'])
        ->and($dictionaryColumn::class)->toBe(TextColumn::class)
        ->and($dictionaryColumn->formatState(1))->toBe('Reviewer dictionary')
        ->and($dictionaryColumn->getColor(1))->toBe('success')
        ->and($entries['reviewed_by_id']::class)->toBe(TextEntry::class)
        ->and($entries['reviewed_by_id']->formatState(1))->toBe('Reviewer dictionary');
});

it('keeps morph display heterogeneous even when its source implements Authenticatable', function (): void {
    Schema::table('user_field_records', fn (Blueprint $table) => $table->string('name')->default('Referring identity'));
    $identity = UserFieldIdentity::create(['name' => 'Morph identity']);
    $owner = Owner::create(['name' => 'Morph owner']);
    $title = UserFieldTitleIdentity::create(['title' => 'Morph title']);

    foreach ([$identity, $owner, $title] as $target) {
        $record = new UserFieldAuthenticatableRecord(['user_id' => $identity->id]);
        $record->subject()->associate($target);
        $record->save();
        $record->refresh();
        $fields = FormGenerator::makeForModel($record, relationManagerView: true);
        $column = mountUserFieldColumn(TableGenerator::makeForModel($record)['subject'], $record);
        $entries = userFieldEntries(InfolistGenerator::makeForModel($record), $record);
        $entry = collect($entries)->first(fn (TextEntry $entry): bool => str_starts_with($entry->getName(), 'subject.'));
        $expected = $target->getAttribute('name') ?? $target->getAttribute('title');

        expect($fields)->toHaveKeys(['subject_type', 'subject_id'])
            ->and($fields['subject_id']::class)->toBe(TextInput::class)
            ->and($column::class)->toBe(TextColumn::class)
            ->and($column->getState())->toBe($expected)
            ->and($entry::class)->toBe(TextEntry::class)
            ->and($entry->getState())->toBe($expected)
            ->and($column->toHtml())->not->toContain('<img')
            ->and($entry->toHtml())->not->toContain('<img');
    }
});

it('uses real user fields in standalone and resource-backed relation managers', function (bool $resourceBacked): void {
    $class = $resourceBacked
        ? RelationManagerMounter::makeFromResource(UserFieldRecordResource::class, 'userFieldRecords', associateAttachActions: false)
        : RelationManagerMounter::makeStandalone('userFieldRecords', visibleColumns: ['user.name', 'author.name'], searchableColumns: ['user.name' => 'both'], associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = UserFieldOwner::create(['name' => 'Manager owner']);
    $manager->pageClass = UserFieldListRecords::class;
    $form = $manager->form(FilamentSchema::make($manager)->model(UserFieldRecord::class));
    $fields = collect($form->getComponents())->keyBy(fn ($field): string => $field->getName());
    $table = Table::make($manager)->relationship(fn () => $manager->getRelationship());
    if ($resourceBacked) {
        $table = UserFieldRecordResource::table($table);
    }
    $table = $manager->table($table);
    $entries = userFieldEntries($manager->infolist(FilamentSchema::make($manager)->model(UserFieldRecord::class))->getComponents());

    expect($fields)->not->toHaveKey('owner_id')
        ->and($fields['user_id'])->toBeInstanceOf(UserSelect::class)
        ->and($fields['author_id'])->toBeInstanceOf(UserSelect::class)
        ->and($table->getColumns())->not->toHaveKey('owner.name')
        ->and($table->getColumns()['user.name'])->toBeInstanceOf(UserColumn::class)
        ->and($table->getColumns()['user.name']->isGloballySearchable())->toBeTrue()
        ->and($entries)->not->toHaveKey('owner.name')
        ->and($entries['user.name'])->toBeInstanceOf(UserEntry::class);
})->with(['standalone' => [false], 'resource-backed' => [true]]);

it('detects and queries user fields on the current model connection with a table prefix', function (): void {
    UserFieldFixtures::create('secondary');

    try {
        Schema::table('user_field_identities', fn (Blueprint $table) => $table->renameColumn('name', 'title'));
        $identity = (new UserFieldIdentity())->setConnection('secondary');
        $identity->fill(['name' => 'Secondary <b>identity</b> & Co'])->save();
        $model = (new UserFieldRecord())->setConnection('secondary');
        $model->fill(['user_id' => $identity->id])->save();
        $related = $model->user()->getRelated();
        $fields = FormGenerator::makeForModel($model, exceptColumns: ['owner_id'], relationManagerView: true);
        FilamentSchema::make(new UserFieldListRecords())->model($model)->components($fields)->getFlatComponents(withHidden: true);
        $columns = TableGenerator::makeForModel($model, exceptColumns: ['owner_id']);
        $entries = userFieldEntries(InfolistGenerator::makeForModel($model, exceptColumns: ['owner_id']), $model);

        expect($related->getConnectionName())->toBe('secondary')
            ->and($related->getConnection()->getTablePrefix())->toBe('secondary_')
            ->and(RelationshipGuesser::guessBelongsTo('user_id', $model))->toBe(['user', 'name'])
            ->and($fields['user_id'])->toBeInstanceOf(UserSelect::class)
            ->and($columns['user_id'])->toBeInstanceOf(UserColumn::class)
            ->and($columns['user_id']->getName())->toBe('user.name')
            ->and($entries['user.name'])->toBeInstanceOf(UserEntry::class)
            ->and(FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['user_id']::class)->toBe(Select::class);
        assertUserFieldHtml((string) $fields['user_id']->getSearchResults('SECONDARY')[$identity->id], $identity, $identity->name);
        assertUserFieldHtml(mountUserFieldColumn($columns['user_id'], $model)->toHtml(), $identity, $identity->name);
        assertUserFieldHtml($entries['user.name']->toHtml(), $identity, $identity->name);
    } finally {
        UserFieldFixtures::drop('secondary');
    }
});
