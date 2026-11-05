<?php

use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldAccessorIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldAuthenticatableRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldKeyIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldNamedIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldNonUserRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldOrphanRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldPlainIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldPlainRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldRecordResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldTitleIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldUnverifiedRecord;

require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';

beforeEach(function (): void {
    UserFieldFixtures::create();
    UserFieldIdentity::$avatarCalls = 0;
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

function userFieldDom(string $html): DOMXPath
{
    $document = new DOMDocument();
    expect($document->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
    ))->toBeTrue();

    return new DOMXPath($document);
}

function assertUserFieldText(string $html, string $name): void
{
    $dom = userFieldDom($html);

    expect($dom->document->textContent)->toContain($name)
        ->and($dom->query('//img | //script | //iframe | //object | //embed')->length)->toBe(0)
        ->and($html)->not->toContain('ui-avatars.com', '/has-avatar-fallback/')
        ->and(UserFieldIdentity::$avatarCalls)->toBe(0);
}

function assertUserFieldAvatar(string $html, string $url, string $name): DOMElement
{
    $dom = userFieldDom($html);
    $images = $dom->query('//img');

    expect($images->length)->toBe(1)
        ->and($dom->document->textContent)->toContain($name)
        ->and($dom->query('//script | //iframe | //object | //embed')->length)->toBe(0)
        ->and($dom->query('//*[@onerror or @onload or @onclick or @onfocus or @autofocus]')->length)->toBe(0)
        ->and($html)->not->toContain('ui-avatars.com', '/has-avatar-fallback/')
        ->and(UserFieldIdentity::$avatarCalls)->toBe(0);
    $image = $images->item(0);

    expect($image->getAttribute('src'))->toBe($url)
        ->and(preg_split('/\s+/', trim($image->getAttribute('class'))))->toContain('fi-avatar', 'fi-circular', 'fi-size-sm');

    return $image;
}

function assertUserFieldOptionLabel(string $html, Model $identity, string $title = 'name'): void
{
    $expected = '<b>#' . e((string) $identity->getKey()) . '</b>';
    if ($title !== $identity->getKeyName()) {
        $expected .= ' - ' . e((string) $identity->getAttribute($title));
    }

    expect($html)->toContain($expected);
    $dom = userFieldDom($html);
    expect($dom->query('//b')->length)->toBe(1)
        ->and($dom->query('//b')->item(0)->textContent)->toBe('#' . $identity->getKey());
}

it('does not require the removed user-fields dependency', function (): void {
    expect(\Composer\InstalledVersions::isInstalled('zvizvi/user-fields'))->toBeFalse();
});

it('maps authenticatable foreign keys to native selects and entries and the project user column', function (string $foreignKey, string $relationship, string $label): void {
    $fields = FormGenerator::make(UserFieldRecord::class, relationManagerView: true);
    FilamentSchema::make(new UserFieldListRecords())->model(UserFieldRecord::class)->components($fields)->getFlatComponents(withHidden: true);
    $columns = TableGenerator::make(UserFieldRecord::class);
    $entries = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class));

    expect(RelationshipGuesser::guessBelongsTo($foreignKey, new UserFieldRecord()))->toBe([$relationship, 'name'])
        ->and($fields[$foreignKey]::class)->toBe(Select::class)
        ->and($fields[$foreignKey]->getName())->toBe($foreignKey)
        ->and($fields[$foreignKey]->getRelationshipName())->toBe($relationship)
        ->and($fields[$foreignKey]->getRelationshipTitleAttribute())->toBe('name')
        ->and($fields[$foreignKey]->getLabel())->toBe($label)
        ->and($columns[$foreignKey]::class)->toBe(UserColumn::class)
        ->and($columns[$foreignKey]->getName())->toBe("{$relationship}.name")
        ->and($columns[$foreignKey]->getLabel())->toBe($label)
        ->and($entries["{$relationship}.name"]::class)->toBe(TextEntry::class)
        ->and($entries["{$relationship}.name"]->getLabel())->toBe($label);
})->with([
    'conventional user' => ['user_id', 'user', 'User'],
    'author alias' => ['author_id', 'author', 'Author'],
    'camel case relationship' => ['reviewed_by_id', 'reviewedBy', 'Reviewed by'],
]);

it('preserves nullability searchable and case-insensitive relationship select configuration', function (): void {
    $fields = FormGenerator::make(UserFieldRecord::class, relationManagerView: true);

    foreach (['user_id' => true, 'author_id' => false] as $name => $required) {
        expect($fields[$name]::class)->toBe(Select::class)
            ->and($fields[$name]->isRequired())->toBe($required)
            ->and($fields[$name]->isSearchable())->toBeTrue()
            ->and($fields[$name]->isSearchForcedCaseInsensitive())->toBeTrue();
    }
});

it('searches native user options case-insensitively with escaped rich labels and avatars', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Alice <script>alert("user-field")</script> & Co', 'profile_photo_url' => '/avatars/alice.svg']);
    UserFieldIdentity::create(['name' => 'Unrelated person']);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['author_id'];
    FilamentSchema::make(new UserFieldListRecords())->model(UserFieldRecord::class)->components([$field])->getFlatComponents(withHidden: true);

    expect($field::class)->toBe(Select::class)
        ->and(array_keys($field->getSearchResults('aLiCe')))->toBe([$identity->getKey()])
        ->and($field->isHtmlAllowed())->toBeTrue()
        ->and($field->isNative())->toBeFalse()
        ->and(UserFieldIdentity::$avatarCalls)->toBe(0);
    $label = $field->getSearchResults('aLiCe')[$identity->getKey()];
    assertUserFieldOptionLabel($label, $identity);
    assertUserFieldAvatar($label, $identity->profile_photo_url, $identity->name);
});

it('hydrates a selected user option with its escaped rich relationship label', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Selected <b>user</b> & Co', 'profile_photo_url' => '/avatars/selected.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['author_id'];
    $host = new UserFieldListRecords();
    $schema = FilamentSchema::make($host)->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill($record->attributesToArray());

    expect($field::class)->toBe(Select::class)
        ->and((int) $host->userFieldData['author_id'])->toBe($identity->id)
        ->and($field->isHtmlAllowed())->toBeTrue()
        ->and($field->isNative())->toBeFalse()
        ->and(UserFieldIdentity::$avatarCalls)->toBe(0);
    assertUserFieldOptionLabel($field->getOptionLabel(), $identity);
    assertUserFieldAvatar($field->getOptionLabel(), $identity->profile_photo_url, $identity->name);
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

    expect($fields['user_id']::class)->toBe(Select::class);
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

    expect($field::class)->toBe(Select::class)
        ->and(fn () => $schema->getState())->toThrow(ValidationException::class)
        ->and(UserFieldRecord::count())->toBe(0);
});

it('renders an escaped native text entry without an avatar even when one is available', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Display <script>alert("user-field")</script> & Co', 'profile_photo_url' => '/avatars/display.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)['author.name'];

    expect($entry::class)->toBe(TextEntry::class)
        ->and($entry->getState())->toBe($identity->name);
    assertUserFieldText($entry->toHtml(), $identity->name);
});

it('renders the default avatar attribute beside the actual related name through a native text column prefix', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Author <b>identity</b> & Co', 'profile_photo_url' => '/avatars/author.svg?size=32&theme=light']);
    $differentUser = UserFieldIdentity::create(['name' => 'Not the author', 'profile_photo_url' => '/avatars/not-author.svg']);
    $record = UserFieldRecord::create(['user_id' => $differentUser->id, 'author_id' => $identity->id]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['author_id'], $record);

    expect(get_parent_class($column))->toBe(TextColumn::class)
        ->and($column->getName())->toBe('author.name')
        ->and($column->getState())->toBe($identity->name)
        ->and($column->getAvatarAttribute())->toBe('profile_photo_url')
        ->and($column->getPrefix())->toBeInstanceOf(HtmlString::class);
    $image = assertUserFieldAvatar($column->toHtml(), $identity->profile_photo_url, $identity->name);
    expect($image->getAttribute('style'))->toMatch('/(?:^|;)\s*width\s*:\s*\d*\.?\d+(?:px|rem|em)\b/i')
        ->and($image->getAttribute('style'))->toMatch('/(?:^|;)\s*height\s*:\s*\d*\.?\d+(?:px|rem|em)\b/i');
});

it('renders authenticatable relationships without requiring the HasAvatar contract', function (): void {
    $identity = UserFieldPlainIdentity::create(['name' => 'Plain authenticatable', 'profile_photo_url' => '/avatars/plain.svg']);
    $record = UserFieldPlainRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldPlainRecord::class)['author_id'], $record);

    expect($identity)->not->toBeInstanceOf(\Filament\Models\Contracts\HasAvatar::class)
        ->and($column::class)->toBe(UserColumn::class)
        ->and($column->getName())->toBe('author.name');
    assertUserFieldAvatar($column->toHtml(), $identity->profile_photo_url, $identity->name);
});

it('uses a custom avatar attribute through the fluent column API', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Custom portrait', 'profile_photo_url' => '/avatars/default.svg', 'portrait_url' => '/portraits/custom.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $column = TableGenerator::make(UserFieldRecord::class)['author_id'];

    expect($column->avatarAttribute('portrait_url'))->toBe($column)
        ->and($column->getAvatarAttribute())->toBe('portrait_url');
    assertUserFieldAvatar(mountUserFieldColumn($column, $record)->toHtml(), $identity->portrait_url, $identity->name);
});

it('applies native configureUsing callbacks to generated columns without leaking configuration', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Configured portrait', 'profile_photo_url' => '/avatars/default.svg', 'portrait_url' => '/portraits/configured.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);

    UserColumn::configureUsing(
        fn (UserColumn $column) => $column->avatarAttribute('portrait_url'),
        during: function () use ($record, $identity): void {
            $generated = TableGenerator::make(UserFieldRecord::class)['author_id'];
            expect($generated->getAvatarAttribute())->toBe('portrait_url');
            assertUserFieldAvatar(mountUserFieldColumn($generated, $record)->toHtml(), $identity->portrait_url, $identity->name);

            $host = new UserFieldListRecords();
            $resourceColumn = UserFieldRecordResource::table(Table::make($host)->query(UserFieldRecord::query()))->getColumns()['author.name'];
            expect($resourceColumn->getAvatarAttribute())->toBe('portrait_url');
            assertUserFieldAvatar(mountUserFieldColumn($resourceColumn, $record)->toHtml(), $identity->portrait_url, $identity->name);
        },
    );

    $column = TableGenerator::make(UserFieldRecord::class)['author_id'];
    expect($column->getAvatarAttribute())->toBe('profile_photo_url');
    assertUserFieldAvatar(mountUserFieldColumn($column, $record)->toHtml(), $identity->profile_photo_url, $identity->name);
});

it('reads default and configured avatar accessors without requiring database columns', function (string $attribute, string $prefix): void {
    $identity = UserFieldAccessorIdentity::create(['title' => 'Accessor avatar']);
    $required = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $required->id, 'accessor_user_id' => $identity->id]);
    $column = TableGenerator::make(UserFieldRecord::class)['accessor_user_id']->avatarAttribute($attribute);
    $preventMissing = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        expect(Schema::hasColumn($identity->getTable(), $attribute))->toBeFalse();
        assertUserFieldAvatar(mountUserFieldColumn($column, $record)->toHtml(), $prefix . $identity->id . '.svg', $identity->title);
    } finally {
        Model::preventAccessingMissingAttributes($preventMissing);
    }
})->with([
    'default accessor' => ['profile_photo_url', '/accessor-avatars/'],
    'custom accessor' => ['portrait_url', '/accessor-portraits/'],
]);

it('escapes names and avatar URL attributes without creating injected DOM nodes', function (string $name, string $url, string $expectedUrl): void {
    $identity = UserFieldIdentity::create(['name' => $name, 'profile_photo_url' => $url]);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['author_id'], $record);

    assertUserFieldAvatar($column->toHtml(), $expectedUrl, $name);
})->with([
    'name markup' => ['Alice </span><script>alert("name")</script><img src=x onerror="alert(1)"> & Co', '/avatars/safe.svg', '/avatars/safe.svg'],
    'quoted URL' => ['Quoted avatar', '/avatars/alice.svg?label=" onerror="alert(1)&next=\' onload=\'alert(2)', '/avatars/alice.svg?label="%20onerror="alert(1)&next=\'%20onload=\'alert(2)'],
    'URL markup' => ['URL markup', '/avatars/alice.svg?label="><script>alert(1)</script>&size=32', '/avatars/alice.svg?label="><script>alert(1)</script>&size=32'],
    'entity text' => ['Alice &quot; &lt;script&gt; & Co', '/avatars/alice.svg?a=1&b=2', '/avatars/alice.svg?a=1&b=2'],
]);

it('accepts HTTP HTTPS and relative avatar URLs', function (string $url): void {
    $identity = UserFieldIdentity::create(['name' => 'Safe URL', 'profile_photo_url' => $url]);
    $record = UserFieldRecord::create(['user_id' => $identity->id]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['user_id'], $record);

    assertUserFieldAvatar($column->toHtml(), $url, $identity->name);
})->with([
    'https' => ['https://example.test/avatars/alice.svg?size=32&theme=light'],
    'http' => ['http://example.test/avatars/alice.svg'],
    'root relative' => ['/avatars/alice.svg'],
    'path relative' => ['avatars/alice.svg'],
    'parent relative' => ['../avatars/alice.svg'],
]);

it('rejects unsafe avatar URL schemes without falling back to HasAvatar', function (string $url): void {
    $identity = UserFieldIdentity::create(['name' => 'Unsafe <b>avatar</b>', 'profile_photo_url' => $url]);
    $record = UserFieldRecord::create(['user_id' => $identity->id]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['user_id'], $record);

    assertUserFieldText($column->toHtml(), $identity->name);
})->with([
    'javascript' => ['javascript:alert(1)'],
    'mixed case javascript' => ['JaVaScRiPt:alert(1)'],
    'leading whitespace' => [" \tjavascript:alert(1)"],
    'embedded control character' => ["java\nscript:alert(1)"],
    'data' => ['data:image/svg+xml,<svg onload="alert(1)"></svg>'],
    'file' => ['file:///etc/passwd'],
    'ftp' => ['ftp://example.test/avatar.png'],
    'vbscript' => ['vbscript:msgbox(1)'],
]);

it('renders plain names for blank avatar attributes instead of using another attribute or HasAvatar', function (string $attribute, ?string $url): void {
    $identity = UserFieldIdentity::create([
        'name' => 'No avatar',
        'profile_photo_url' => '/avatars/not-a-fallback.svg',
        'portrait_url' => '/portraits/not-a-fallback.svg',
        $attribute => $url,
    ]);
    $record = UserFieldRecord::create(['user_id' => $identity->id]);
    $column = TableGenerator::make(UserFieldRecord::class)['user_id']->avatarAttribute($attribute);

    assertUserFieldText(mountUserFieldColumn($column, $record)->toHtml(), $identity->name);
})->with([
    'default null' => ['profile_photo_url', null],
    'default empty' => ['profile_photo_url', ''],
    'default whitespace' => ['profile_photo_url', " \t\n"],
    'custom null' => ['portrait_url', null],
    'custom empty' => ['portrait_url', ''],
    'custom whitespace' => ['portrait_url', " \t\n"],
]);

it('handles hidden and missing avatar attributes under strict missing-attribute mode', function (string $scenario): void {
    $identity = UserFieldIdentity::create(['name' => 'Strict identity', 'profile_photo_url' => '/avatars/hidden.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id]);
    $related = $scenario === 'not selected'
        ? UserFieldIdentity::query()->select(['id', 'name'])->findOrFail($identity->id)
        : $identity->fresh()->makeHidden(['profile_photo_url']);
    $record->setRelation('user', $related);
    $column = TableGenerator::make(UserFieldRecord::class)['user_id'];
    if ($scenario === 'nonexistent attribute') {
        $column->avatarAttribute('missing_avatar_url');
    }
    $preventMissing = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $html = mountUserFieldColumn($column, $record)->toHtml();
        if ($scenario === 'hidden') {
            expect($related->toArray())->not->toHaveKey('profile_photo_url');
            assertUserFieldAvatar($html, '/avatars/hidden.svg', $identity->name);
        } else {
            assertUserFieldText($html, $identity->name);
        }
    } finally {
        Model::preventAccessingMissingAttributes($preventMissing);
    }
})->with(['hidden', 'not selected', 'nonexistent attribute']);

it('retains native text formatting suffixes and placeholders', function (): void {
    $identity = UserFieldIdentity::create(['name' => 'Native <b>formatting</b>', 'profile_photo_url' => '/avatars/native.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $identity->id]);
    $column = UserColumn::make('author.name')
        ->formatStateUsing(fn (string $state): string => 'Formatted: ' . $state)
        ->suffix(' <suffix>')
        ->placeholder('No author');
    mountUserFieldColumn($column, $record);

    assertUserFieldAvatar($column->toHtml(), $identity->profile_photo_url, 'Formatted: ' . $identity->name . ' <suffix>');
    $withoutAuthor = UserFieldRecord::create(['user_id' => $identity->id]);
    assertUserFieldText($column->record($withoutAuthor)->toHtml(), 'No author');
});

it('retains native related-name search sorting and eager loading without extra avatar queries', function (): void {
    $zulu = UserFieldIdentity::create(['name' => 'Zulu', 'profile_photo_url' => '/avatars/zulu.svg']);
    $alpha = UserFieldIdentity::create(['name' => 'Alpha', 'profile_photo_url' => '/avatars/alpha.svg']);
    $last = UserFieldRecord::create(['user_id' => $zulu->id]);
    $first = UserFieldRecord::create(['user_id' => $alpha->id]);
    $column = TableGenerator::make(UserFieldRecord::class, searchableColumns: ['user.name' => 'both'])['user_id'];
    mountUserFieldColumn($column, $last);

    expect($column::class)->toBe(UserColumn::class)
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
            assertUserFieldAvatar($column->record($record)->toHtml(), $record->user->profile_photo_url, $record->user->name);
        }
        expect(count($connection->getQueryLog()))->toBe($queryCount);
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
});

it('renders eager-loaded avatar accessors without issuing additional queries', function (string $attribute): void {
    $required = UserFieldIdentity::create(['name' => 'Required identity']);
    foreach (['First accessor', 'Second accessor'] as $title) {
        $identity = UserFieldAccessorIdentity::create(['title' => $title]);
        UserFieldRecord::create(['user_id' => $required->id, 'accessor_user_id' => $identity->id]);
    }
    $column = TableGenerator::make(UserFieldRecord::class)['accessor_user_id']->avatarAttribute($attribute);
    mountUserFieldColumn($column, new UserFieldRecord());
    $connection = $required->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    try {
        $records = $column->applyEagerLoading(UserFieldRecord::query())->get();
        expect(count($connection->getQueryLog()))->toBe(2);
        foreach ($records as $record) {
            expect($record->relationLoaded('accessorUser'))->toBeTrue();
            assertUserFieldAvatar($column->record($record)->toHtml(), $record->accessorUser->getAttribute($attribute), $record->accessorUser->title);
        }
        expect(count($connection->getQueryLog()))->toBe(2);
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
})->with(['profile_photo_url', 'portrait_url']);

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
                ->and($fields[$foreignKey])->toBeInstanceOf(TextInput::class)
                ->and($fields[$foreignKey]->isNumeric())->toBeTrue()
                ->and($columns[$foreignKey]::class)->toBe(TextColumn::class)
                ->and($columns[$foreignKey]->getName())->toBe($foreignKey)
                ->and($entries[$foreignKey]::class)->toBe(TextEntry::class);
        }
    }
});

it('detects authenticatable title and key relationships without requiring name or HasName', function (string $identityClass, array $attributes, string $foreignKey, string $relationship, string $title): void {
    $identity = $identityClass::create($attributes);
    $required = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $required->id, $foreignKey => $identity->id]);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)[$foreignKey];
    $schema = FilamentSchema::make(new UserFieldListRecords())->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill($record->attributesToArray());
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)[$foreignKey], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)["{$relationship}.{$title}"];
    $label = (string) $identity->getAttribute($title);
    $optionLabel = '<b>#' . e((string) $identity->getKey()) . '</b>' . ($title === $identity->getKeyName() ? '' : ' - ' . e($label));

    expect(RelationshipGuesser::guessBelongsTo($foreignKey, $record))->toBe([$relationship, $title])
        ->and($field::class)->toBe(Select::class)
        ->and($field->getRelationshipTitleAttribute())->toBe($title)
        ->and((string) $field->getOptionLabel())->toBe($optionLabel)
        ->and((string) $field->getSearchResults($label)[$identity->id])->toBe($optionLabel)
        ->and($column::class)->toBe(UserColumn::class)
        ->and($column->getName())->toBe("{$relationship}.{$title}")
        ->and((string) $column->getState())->toBe($label)
        ->and($entry::class)->toBe(TextEntry::class)
        ->and((string) $entry->getState())->toBe($label);
    assertUserFieldText($column->toHtml(), $label);
    assertUserFieldText($entry->toHtml(), $label);
})->with([
    'title only' => [UserFieldTitleIdentity::class, ['title' => 'Title only'], 'title_user_id', 'titleUser', 'title'],
    'key only' => [UserFieldKeyIdentity::class, [], 'key_user_id', 'keyUser', 'id'],
]);

it('uses the relationship title rather than automatically applying HasName or a name accessor', function (string $identityClass, string $foreignKey, string $relationship): void {
    $identity = $identityClass::create(['title' => 'Title <b>identity</b> & Co']);
    $required = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $required->id, $foreignKey => $identity->id]);
    $field = FormGenerator::make(UserFieldRecord::class, relationManagerView: true)[$foreignKey];
    $schema = FilamentSchema::make(new UserFieldListRecords())->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill($record->attributesToArray());
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)[$foreignKey], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)["{$relationship}.title"];

    expect($field::class)->toBe(Select::class)
        ->and($field->getRelationshipTitleAttribute())->toBe('title')
        ->and(array_keys($field->getSearchResults('Title')))->toBe([$identity->id])
        ->and($field->getOptionLabel())->toBe($field->getSearchResults('Title')[$identity->id])
        ->and($column::class)->toBe(UserColumn::class)
        ->and($column->getName())->toBe("{$relationship}.title")
        ->and($column->getState())->toBe($identity->title)
        ->and($entry::class)->toBe(TextEntry::class)
        ->and($entry->getState())->toBe($identity->title);
    assertUserFieldOptionLabel($field->getOptionLabel(), $identity, 'title');
    expect(userFieldDom($field->getOptionLabel())->document->textContent)->not->toContain('Display: ', 'Accessor: ');
    assertUserFieldText($entry->toHtml(), $identity->title);
    expect(userFieldDom($column->toHtml())->document->textContent)->toContain($identity->title)->not->toContain('Display: ', 'Accessor: ');
})->with([
    'HasName contract' => [UserFieldNamedIdentity::class, 'named_user_id', 'namedUser'],
    'name accessor' => [UserFieldAccessorIdentity::class, 'accessor_user_id', 'accessorUser'],
]);

it('honors explicit native relationship title and accessor label overrides', function (): void {
    $identity = UserFieldAccessorIdentity::create(['title' => 'Overridden title']);
    $required = UserFieldIdentity::create(['name' => 'Required identity']);
    $record = UserFieldRecord::create(['user_id' => $required->id, 'accessor_user_id' => $identity->id]);
    $fieldOverride = Select::make('accessor_user_id')->relationship('accessorUser', 'title')->searchable()
        ->getOptionLabelFromRecordUsing(fn (Model $record): string => $record->name);
    $columnOverride = UserColumn::make('accessorUser.name');
    $entryOverride = TextEntry::make('accessorUser.name');
    $field = FormGenerator::make(UserFieldRecord::class, overwriteColumns: ['accessor_user_id' => $fieldOverride], relationManagerView: true)['accessor_user_id'];
    $schema = FilamentSchema::make(new UserFieldListRecords())->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill($record->attributesToArray());
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class, overwriteColumns: ['accessor_user_id' => $columnOverride])['accessor_user_id'], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class, overwriteColumns: ['accessor_user_id' => $entryOverride]), $record)['accessorUser.name'];

    expect($field)->toBe($fieldOverride)
        ->and($field->getSearchResults('Overridden'))->toBe([$identity->id => $identity->name])
        ->and($field->getOptionLabel())->toBe($identity->name)
        ->and($field->isHtmlAllowed())->toBeFalse()
        ->and($column)->toBe($columnOverride)
        ->and($column->getState())->toBe($identity->name)
        ->and($entry)->toBe($entryOverride)
        ->and($entry->getState())->toBe($identity->name);
    assertUserFieldAvatar($column->toHtml(), $identity->profile_photo_url, $identity->name);
    assertUserFieldText($entry->toHtml(), $identity->name);
});

it('renders nullable or missing related users without an avatar or a type error', function (?int $authorId): void {
    $identity = UserFieldIdentity::create(['name' => 'Required identity', 'profile_photo_url' => '/avatars/not-author.svg']);
    $record = UserFieldRecord::create(['user_id' => $identity->id, 'author_id' => $authorId]);
    $column = mountUserFieldColumn(TableGenerator::make(UserFieldRecord::class)['author_id'], $record);
    $entry = userFieldEntries(InfolistGenerator::make(UserFieldRecord::class), $record)['author.name'];

    expect($column::class)->toBe(UserColumn::class)
        ->and($entry::class)->toBe(TextEntry::class)
        ->and(blank($column->getState()))->toBeTrue()
        ->and(blank($entry->getState()))->toBeTrue();
    assertUserFieldText($column->toHtml(), 'null');
    assertUserFieldText($entry->toHtml(), 'null');
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
    $identity = UserFieldIdentity::create(['name' => 'Morph identity', 'profile_photo_url' => '/avatars/not-for-morph.svg']);
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

        expect($fields)->toHaveKey('subject')->not->toHaveKeys(['subject_type', 'subject_id'])
            ->and($fields['subject']::class)->toBe(MorphToSelect::class)
            ->and($column::class)->toBe(TextColumn::class)
            ->and($column->getState())->toBe($expected)
            ->and($entry::class)->toBe(TextEntry::class)
            ->and($entry->getState())->toBe($expected);
        assertUserFieldText($column->toHtml(), $expected);
        assertUserFieldText($entry->toHtml(), $expected);
    }
});

it('uses native user fields in standalone and resource-backed relation managers', function (bool $resourceBacked): void {
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
        ->and($fields['user_id']::class)->toBe(Select::class)
        ->and($fields['author_id']::class)->toBe(Select::class)
        ->and($table->getColumns())->not->toHaveKey('owner.name')
        ->and($table->getColumns()['user.name']::class)->toBe(UserColumn::class)
        ->and($table->getColumns()['user.name']->isGloballySearchable())->toBeTrue()
        ->and($entries)->not->toHaveKey('owner.name')
        ->and($entries['user.name']::class)->toBe(TextEntry::class);
})->with(['standalone' => [false], 'resource-backed' => [true]]);

it('detects and queries user fields on the current model connection with a table prefix', function (): void {
    UserFieldFixtures::create('secondary');

    try {
        Schema::table('user_field_identities', fn (Blueprint $table) => $table->renameColumn('name', 'title'));
        $identity = (new UserFieldIdentity())->setConnection('secondary');
        $identity->fill(['name' => 'Secondary <b>identity</b> & Co', 'profile_photo_url' => '/avatars/secondary.svg'])->save();
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
            ->and($fields['user_id']::class)->toBe(Select::class)
            ->and($columns['user_id']::class)->toBe(UserColumn::class)
            ->and($columns['user_id']->getName())->toBe('user.name')
            ->and($entries['user.name']::class)->toBe(TextEntry::class)
            ->and(FormGenerator::make(UserFieldRecord::class, relationManagerView: true)['user_id']->getRelationshipTitleAttribute())->toBe('title')
            ->and(array_keys($fields['user_id']->getSearchResults('SECONDARY')))->toBe([$identity->id]);
                    $label = $fields['user_id']->getSearchResults('SECONDARY')[$identity->id];
                    assertUserFieldOptionLabel($label, $identity);
                    assertUserFieldAvatar($label, $identity->profile_photo_url, $identity->name);
        assertUserFieldAvatar(mountUserFieldColumn($columns['user_id'], $model)->toHtml(), $identity->profile_photo_url, $identity->name);
        assertUserFieldText($entries['user.name']->toHtml(), $identity->name);
    } finally {
        UserFieldFixtures::drop('secondary');
    }
});
