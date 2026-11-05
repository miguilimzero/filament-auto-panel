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
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17AccessorIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17AuthenticatableRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17Identity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17ListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17NamedIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17NonUserRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17OrphanRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17Record;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17RecordResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17TitleIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17UnverifiedRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17UserFieldFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Zvizvi\UserFields\Components\UserColumn;
use Zvizvi\UserFields\Components\UserEntry;
use Zvizvi\UserFields\Components\UserSelect;

require_once __DIR__ . '/../Fixtures/Issue17UserFieldFixtures.php';

beforeEach(function (): void {
    Issue17UserFieldFixtures::create();
});

afterEach(function (): void {
    Issue17UserFieldFixtures::drop();
});

function issue17Entries(array $components, Model|string $model = Issue17Record::class): array
{
    $schema = FilamentSchema::make(new Issue17ListRecords())->model($model)->components($components);

    return collect($schema->getFlatComponents(withHidden: true))
        ->filter(fn ($component): bool => $component instanceof TextEntry)
        ->keyBy(fn ($entry): string => $entry->getName())
        ->all();
}

function issue17MountColumn(TextColumn $column, Model $record): TextColumn
{
    $host = new Issue17ListRecords();
    $table = Table::make($host)->query($record->newQuery())->columns([$column]);
    $host->setIssue17Table($table);
    $table->getColumns();

    return $column->record($record);
}

function issue17AssertUserHtml(string $html, Issue17Identity $identity, string $name): void
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($text)->toContain($name)
        ->and($html)->toContain('<img', $identity->getFilamentAvatarUrl())
        ->and(strtolower($html))->not->toContain('<script', 'ui-avatars.com');
}

it('loads the mandatory real user-fields components and registered avatar view', function (): void {
    $identity = Issue17Identity::create(['name' => 'Plugin <b>identity</b> & Co']);
    $record = Issue17Record::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $field = UserSelect::make('author_id')->relationship('author', 'name')->searchable();
    FilamentSchema::make(new Issue17ListRecords())->model($record)->components([$field])->getFlatComponents(withHidden: true);

    expect(\Composer\InstalledVersions::isInstalled('zvizvi/user-fields'))->toBeTrue()
        ->and(view()->exists('user-fields::user-avatar-option'))->toBeTrue();
    issue17AssertUserHtml((string) $field->getSearchResults('Plugin')[$identity->id], $identity, $identity->name);
    issue17AssertUserHtml(issue17MountColumn(UserColumn::make('author.name'), $record)->toHtml(), $identity, $identity->name);
    $entry = issue17Entries([UserEntry::make('author.name')], $record)['author.name'];
    issue17AssertUserHtml($entry->toHtml(), $identity, $identity->name);
});

it('maps authenticatable foreign keys to real user fields using the actual relationship', function (string $foreignKey, string $relationship, string $label): void {
    $fields = FormGenerator::make(Issue17Record::class, relationManagerView: true);
    FilamentSchema::make(new Issue17ListRecords())->model(Issue17Record::class)->components($fields)->getFlatComponents(withHidden: true);
    $columns = TableGenerator::make(Issue17Record::class);
    $entries = issue17Entries(InfolistGenerator::make(Issue17Record::class));

    expect(RelationshipGuesser::guessBelongsTo($foreignKey, new Issue17Record()))->toBe([$relationship, 'name'])
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
    $fields = FormGenerator::make(Issue17Record::class, relationManagerView: true);

    foreach (['user_id' => true, 'author_id' => false] as $name => $required) {
        expect($fields[$name])->toBeInstanceOf(UserSelect::class)
            ->and($fields[$name]->isRequired())->toBe($required)
            ->and($fields[$name]->isSearchable())->toBeTrue()
            ->and($fields[$name]->isSearchForcedCaseInsensitive())->toBeTrue();
    }
});

it('searches real user options case-insensitively and renders escaped names with local avatars', function (): void {
    $identity = Issue17Identity::create(['name' => 'Alice <script>alert("issue17")</script> & Co']);
    Issue17Identity::create(['name' => 'Unrelated person']);
    $field = FormGenerator::make(Issue17Record::class, relationManagerView: true)['author_id'];
    FilamentSchema::make(new Issue17ListRecords())->model(Issue17Record::class)->components([$field])->getFlatComponents(withHidden: true);

    expect($field)->toBeInstanceOf(UserSelect::class);
    $results = $field->getSearchResults('aLiCe');

    expect(array_keys($results))->toBe([$identity->getKey()])
        ->and($field->isHtmlAllowed())->toBeTrue();
    issue17AssertUserHtml((string) $results[$identity->getKey()], $identity, $identity->name);
});

it('hydrates a selected user option through the real relationship label renderer', function (): void {
    $identity = Issue17Identity::create(['name' => 'Selected <b>user</b> & Co']);
    $record = Issue17Record::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $field = FormGenerator::make(Issue17Record::class, relationManagerView: true)['author_id'];
    $host = new Issue17ListRecords();
    $schema = FilamentSchema::make($host)->model($record)->statePath('issue17Data')->components([$field]);
    $schema->fill($record->attributesToArray());

    expect($field)->toBeInstanceOf(UserSelect::class)
        ->and((int) $host->issue17Data['author_id'])->toBe($identity->id);
    issue17AssertUserHtml((string) $field->getOptionLabel(), $identity, $identity->name);
});

it('saves generated resource user selects using foreign keys rather than relationship display paths', function (string $operation): void {
    $first = Issue17Identity::create(['name' => 'First identity']);
    $second = Issue17Identity::create(['name' => 'Second identity']);
    $record = $operation === 'edit'
        ? Issue17Record::create(['user_id' => $first->id, 'author_id' => $first->id])->fresh()
        : new Issue17Record();
    $host = new Issue17ListRecords();
    $schema = Issue17RecordResource::form(FilamentSchema::make($host)->model($record)->operation($operation)->statePath('issue17Data'));
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
    $host->issue17Data['user_id'] = $second->id;
    $host->issue17Data['author_id'] = null;
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
    $field = FormGenerator::make(Issue17Record::class, relationManagerView: true)['user_id'];
    $schema = FilamentSchema::make(new Issue17ListRecords())->model(Issue17Record::class)->statePath('issue17Data')->components([$field]);
    $schema->fill(['user_id' => null]);

    expect($field)->toBeInstanceOf(UserSelect::class)
        ->and(fn () => $schema->getState())->toThrow(ValidationException::class)
        ->and(Issue17Record::count())->toBe(0);
});

it('renders real user display HTML with escaped names and local avatars', function (string $surface): void {
    $identity = Issue17Identity::create(['name' => 'Display <script>alert("issue17")</script> & Co']);
    $differentUser = Issue17Identity::create(['name' => 'Not the author']);
    $record = Issue17Record::create(['user_id' => $differentUser->id, 'author_id' => $identity->id]);

    if ($surface === 'table') {
        $component = issue17MountColumn(TableGenerator::make(Issue17Record::class)['author_id'], $record);
        expect($component)->toBeInstanceOf(UserColumn::class);
    } else {
        $component = issue17Entries(InfolistGenerator::make(Issue17Record::class), $record)['author.name'];
        expect($component)->toBeInstanceOf(UserEntry::class);
    }

    issue17AssertUserHtml($component->toHtml(), $identity, $identity->name);
})->with(['table', 'infolist']);

it('retains native related-name search sorting and eager loading for user columns', function (): void {
    $zulu = Issue17Identity::create(['name' => 'Zulu']);
    $alpha = Issue17Identity::create(['name' => 'Alpha']);
    $last = Issue17Record::create(['user_id' => $zulu->id]);
    $first = Issue17Record::create(['user_id' => $alpha->id]);
    $column = TableGenerator::make(Issue17Record::class, searchableColumns: ['user.name' => 'both'])['user_id'];
    issue17MountColumn($column, $last);

    expect($column)->toBeInstanceOf(UserColumn::class)
        ->and($column->getName())->toBe('user.name')
        ->and($column->isGloballySearchable())->toBeTrue()
        ->and($column->isIndividuallySearchable())->toBeTrue();
    $isFirst = true;
    expect($column->applySearchConstraint(Issue17Record::query(), 'Alpha', $isFirst)->pluck('id')->all())->toBe([$first->id]);
    $column->sortable();
    expect($column->applySort(Issue17Record::query(), 'asc')->pluck('id')->all())->toBe([$first->id, $last->id])
        ->and($column->applySort(Issue17Record::query(), 'desc')->pluck('id')->all())->toBe([$last->id, $first->id]);

    $connection = $last->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    try {
        $records = $column->applyEagerLoading(Issue17Record::query())->get();
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
    foreach ([Issue17NonUserRecord::class => 'user_id', Issue17Record::class => 'owner_id'] as $model => $foreignKey) {
        $relationship = $foreignKey === 'user_id' ? 'user' : 'owner';
        $field = FormGenerator::make($model, relationManagerView: true)[$foreignKey];
        $column = TableGenerator::make($model)[$foreignKey];
        $entry = issue17Entries(InfolistGenerator::make($model), $model)["{$relationship}.name"];

        expect($field::class)->toBe(Select::class)
            ->and($field->getRelationshipName())->toBe($relationship)
            ->and($column::class)->toBe(TextColumn::class)
            ->and($column->getName())->toBe("{$relationship}.name")
            ->and($entry::class)->toBe(TextEntry::class);
    }
});

it('leaves orphan user IDs and unverified relationships as ordinary numeric fields', function (): void {
    foreach ([Issue17OrphanRecord::class, Issue17UnverifiedRecord::class] as $model) {
        $fields = FormGenerator::make($model, relationManagerView: true);
        $columns = TableGenerator::make($model);
        $entries = issue17Entries(InfolistGenerator::make($model), $model);

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
    $identity = Issue17TitleIdentity::create(['title' => 'Title only']);
    $user = Issue17Identity::create(['name' => 'Required identity']);
    $record = Issue17Record::create(['user_id' => $user->id, 'title_user_id' => $identity->id]);
    $field = FormGenerator::make(Issue17Record::class, relationManagerView: true)['title_user_id'];
    FilamentSchema::make(new Issue17ListRecords())->model($record)->components([$field])->getFlatComponents(withHidden: true);
    $column = issue17MountColumn(TableGenerator::make(Issue17Record::class)['title_user_id'], $record);
    $entry = issue17Entries(InfolistGenerator::make(Issue17Record::class), $record)['titleUser.title'];

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
    $required = Issue17Identity::create(['name' => 'Required identity']);
    $record = Issue17Record::create(['user_id' => $required->id, $foreignKey => $identity->id]);
    $field = FormGenerator::make(Issue17Record::class, relationManagerView: true)[$foreignKey];
    $schema = FilamentSchema::make(new Issue17ListRecords())->model($record)->statePath('issue17Data')->components([$field]);
    $schema->fill($record->attributesToArray());
    $column = issue17MountColumn(TableGenerator::make(Issue17Record::class)[$foreignKey], $record);
    $entry = issue17Entries(InfolistGenerator::make(Issue17Record::class), $record)["{$relationship}.title"];

    expect($field)->toBeInstanceOf(UserSelect::class)
        ->and($field->getRelationshipTitleAttribute())->toBe('title')
        ->and($column)->toBeInstanceOf(UserColumn::class)
        ->and($column->getName())->toBe("{$relationship}.title")
        ->and($entry)->toBeInstanceOf(UserEntry::class);
    $results = $field->getSearchResults('Title');
    $displayName = $prefix . $identity->title;
    issue17AssertUserHtml((string) $results[$identity->id], $identity, $displayName);
    issue17AssertUserHtml((string) $field->getOptionLabel(), $identity, $displayName);
    issue17AssertUserHtml($column->toHtml(), $identity, $displayName);
    issue17AssertUserHtml($entry->toHtml(), $identity, $displayName);
})->with([
    'HasName contract' => [Issue17NamedIdentity::class, 'named_user_id', 'namedUser', 'Display: '],
    'name accessor' => [Issue17AccessorIdentity::class, 'accessor_user_id', 'accessorUser', 'Accessor: '],
]);

it('renders nullable or missing related users without an avatar or a type error', function (?int $authorId): void {
    $identity = Issue17Identity::create(['name' => 'Required identity']);
    $record = Issue17Record::create(['user_id' => $identity->id, 'author_id' => $authorId]);
    $column = issue17MountColumn(TableGenerator::make(Issue17Record::class)['author_id'], $record);
    $entry = issue17Entries(InfolistGenerator::make(Issue17Record::class), $record)['author.name'];

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
    $fields = FormGenerator::make(Issue17Record::class, exceptColumns: ['user_id'], overwriteColumns: ['user_id' => TextInput::make('user_id'), 'author_id' => $formOverride], enumDictionary: $dictionary, relationManagerView: true);
    $columns = TableGenerator::make(Issue17Record::class, exceptColumns: ['user_id'], overwriteColumns: ['user_id' => TextColumn::make('user_id'), 'author_id' => $tableOverride], enumDictionary: $dictionary);
    $entries = issue17Entries(InfolistGenerator::make(Issue17Record::class, exceptColumns: ['user_id'], overwriteColumns: ['user_id' => TextEntry::make('user_id'), 'author_id' => $entryOverride], enumDictionary: $dictionary));
    $record = new Issue17Record(['reviewed_by_id' => 1]);
    $dictionaryColumn = issue17MountColumn($columns['reviewed_by_id'], $record);

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
    Schema::table('issue17_records', fn (Blueprint $table) => $table->string('name')->default('Referring identity'));
    $identity = Issue17Identity::create(['name' => 'Morph identity']);
    $owner = Owner::create(['name' => 'Morph owner']);
    $title = Issue17TitleIdentity::create(['title' => 'Morph title']);

    foreach ([$identity, $owner, $title] as $target) {
        $record = new Issue17AuthenticatableRecord(['user_id' => $identity->id]);
        $record->subject()->associate($target);
        $record->save();
        $record->refresh();
        $fields = FormGenerator::makeForModel($record, relationManagerView: true);
        $column = issue17MountColumn(TableGenerator::makeForModel($record)['subject'], $record);
        $entries = issue17Entries(InfolistGenerator::makeForModel($record), $record);
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
        ? RelationManagerMounter::makeFromResource(Issue17RecordResource::class, 'issue17Records', associateAttachActions: false)
        : RelationManagerMounter::makeStandalone('issue17Records', visibleColumns: ['user.name', 'author.name'], searchableColumns: ['user.name' => 'both'], associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = Issue17Owner::create(['name' => 'Manager owner']);
    $manager->pageClass = Issue17ListRecords::class;
    $form = $manager->form(FilamentSchema::make($manager)->model(Issue17Record::class));
    $fields = collect($form->getComponents())->keyBy(fn ($field): string => $field->getName());
    $table = Table::make($manager)->relationship(fn () => $manager->getRelationship());
    if ($resourceBacked) {
        $table = Issue17RecordResource::table($table);
    }
    $table = $manager->table($table);
    $entries = issue17Entries($manager->infolist(FilamentSchema::make($manager)->model(Issue17Record::class))->getComponents());

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
    Issue17UserFieldFixtures::create('secondary');

    try {
        Schema::table('issue17_identities', fn (Blueprint $table) => $table->renameColumn('name', 'title'));
        $identity = (new Issue17Identity())->setConnection('secondary');
        $identity->fill(['name' => 'Secondary <b>identity</b> & Co'])->save();
        $model = (new Issue17Record())->setConnection('secondary');
        $model->fill(['user_id' => $identity->id])->save();
        $related = $model->user()->getRelated();
        $fields = FormGenerator::makeForModel($model, exceptColumns: ['owner_id'], relationManagerView: true);
        FilamentSchema::make(new Issue17ListRecords())->model($model)->components($fields)->getFlatComponents(withHidden: true);
        $columns = TableGenerator::makeForModel($model, exceptColumns: ['owner_id']);
        $entries = issue17Entries(InfolistGenerator::makeForModel($model, exceptColumns: ['owner_id']), $model);

        expect($related->getConnectionName())->toBe('secondary')
            ->and($related->getConnection()->getTablePrefix())->toBe('secondary_')
            ->and(RelationshipGuesser::guessBelongsTo('user_id', $model))->toBe(['user', 'name'])
            ->and($fields['user_id'])->toBeInstanceOf(UserSelect::class)
            ->and($columns['user_id'])->toBeInstanceOf(UserColumn::class)
            ->and($columns['user_id']->getName())->toBe('user.name')
            ->and($entries['user.name'])->toBeInstanceOf(UserEntry::class)
            ->and(FormGenerator::make(Issue17Record::class, relationManagerView: true)['user_id']::class)->toBe(Select::class);
        issue17AssertUserHtml((string) $fields['user_id']->getSearchResults('SECONDARY')[$identity->id], $identity, $identity->name);
        issue17AssertUserHtml(issue17MountColumn($columns['user_id'], $model)->toHtml(), $identity, $identity->name);
        issue17AssertUserHtml($entries['user.name']->toHtml(), $identity, $identity->name);
    } finally {
        Issue17UserFieldFixtures::drop('secondary');
    }
});
