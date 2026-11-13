<?php

use Filament\Forms\Components\Select;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Filament\Filters\RelatedRecordOperator;
use Miguilim\FilamentAutoPanel\Generators\FilterGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\ListProducts;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipFilterLabelFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipFilterLabelFormHost;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipFilterLabelParent;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipFilterLabelRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipFilterLabelResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipFilterLabelScopedRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelEncryptedEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelHashedEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelHiddenEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelJoinedUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelKeyUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelNonvisibleEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelPartialUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelUser;

require_once __DIR__ . '/../Fixtures/RelationshipFilterLabelFixtures.php';

beforeEach(function (): void {
    $this->app['translator']->addNamespace('filament-query-builder', __DIR__ . '/../../vendor/filament/query-builder/resources/lang');
    RelationshipFilterLabelFixtures::create();
    RelationshipLabelUser::$avatarCalls = 0;
});

afterEach(function (): void {
    RelationshipFilterLabelFixtures::drop();
});

function relationshipFilterLabelTable(string $surface = 'resource'): Table
{
    $host = new RelationshipFilterLabelFormHost();

    if ($surface === 'resource') {
        return RelationshipFilterLabelResource::table(Table::make($host)->query(RelationshipFilterLabelRecord::query()));
    }

    $class = $surface === 'standalone manager'
        ? RelationManagerMounter::makeStandalone(relation: 'records', visibleColumns: [], readOnly: true, associateAttachActions: false)
        : RelationManagerMounter::makeFromResource(resource: RelationshipFilterLabelResource::class, relation: 'records', associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = RelationshipFilterLabelParent::create(['name' => 'Parent']);
    $manager->pageClass = ListProducts::class;
    $table = Table::make($manager)->relationship(fn () => $manager->getRelationship());
    $resource = $manager::getRelatedResource();

    return $manager->table($resource ? $resource::table($table) : $table);
}

function relationshipFilterLabelOperator(?Table $table = null, string $relationship = 'owner', string $model = RelationshipFilterLabelRecord::class): RelatedRecordOperator
{
    $table ??= Table::make(new RelationshipFilterLabelFormHost())->query($model::query())->filters(FilterGenerator::make($model));
    $constraint = $table->getFilter('queryBuilder')->getConstraints()[$relationship];

    return $constraint->getOperator('isRelatedTo')->constraint($constraint);
}

function relationshipFilterLabelSelect(?RelatedRecordOperator $operator = null): Select
{
    $field = ($operator ?? relationshipFilterLabelOperator())->getFormSchema()[0];
    FilamentSchema::make(new RelationshipFilterLabelFormHost())
        ->statePath('relationshipFilterLabelData')
        ->components([$field])
        ->getFlatComponents(withHidden: true);

    return $field;
}

function relationshipFilterLabelVariants(Select $field, array $keys, string $search): array
{
    $field->state($keys);
    $selected = $field->getOptionLabels();
    $results = $field->getSearchResults($search);
    $preloaded = $field->preload()->getOptions();

    expect($selected)->toHaveCount(count($keys))
        ->and($field->getState())->toBe($keys);

    foreach ($keys as $key) {
        expect($selected)->toHaveKey($key)
            ->and($results)->toHaveKey($key)
            ->and($preloaded)->toHaveKey($key)
            ->and($results[$key])->toBe($selected[$key])
            ->and($preloaded[$key])->toBe($selected[$key]);
    }

    return $selected;
}

function relationshipFilterLabelDom(string $html): DOMXPath
{
    $document = new DOMDocument();
    $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

it('uses rich native multiple relationship selects in resource and both relation manager query builders', function (string $surface): void {
    RelationshipLabelUser::create(['id' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.test', 'profile_photo_url' => '/avatars/alice.svg']);
    RelationshipLabelUser::create(['id' => 'bob', 'name' => 'Bob', 'email' => 'bob@example.test']);
    $table = relationshipFilterLabelTable($surface);
    $constraints = $table->getFilter('queryBuilder')->getConstraints();
    expect($constraints)->toHaveKeys(['owner', 'contact']);
    if ($surface !== 'resource') {
        expect($constraints)->not->toHaveKeys(['parent', 'parent_id']);
    }
    $operator = relationshipFilterLabelOperator($table);
    $field = relationshipFilterLabelSelect($operator);

    expect($operator)->toBeInstanceOf(IsRelatedToOperator::class)
        ->and($field)->toBeInstanceOf(Select::class)
        ->and($field->isHtmlAllowed())->toBeTrue()
        ->and($field->isNative())->toBeFalse()
        ->and($field->isMultiple())->toBeTrue()
        ->and($field->isSearchForcedCaseInsensitive())->toBeTrue()
        ->and($field->getName())->toBe('value')
        ->and($field->getSearchColumns())->toBe(['name', 'id', 'title', 'email']);

    $labels = relationshipFilterLabelVariants($field, ['alice', 'bob'], 'EXAMPLE.TEST');
    $dom = relationshipFilterLabelDom($labels['alice']);
    expect($dom->query('//b')->item(0)->textContent)->toBe('#alice')
        ->and($dom->query('//img')->item(0)->getAttribute('src'))->toBe('/avatars/alice.svg')
        ->and($dom->query('//span[@class="fi-sc-text"]')->item(0)->textContent)->toBe('alice@example.test')
        ->and($dom->query('//span[@class="fi-sc-text"]')->item(0)->getAttribute('style'))->toBe('display:block')
        ->and($field->getSearchResults('ALICE'))->toBe(['alice' => $labels['alice']]);
    $operator->settings(['value' => ['alice', 'bob']])->inverse(false);
    expect($operator->getSummary())->toContain('Alice', 'Bob')->not->toContain('<b>', '<img', 'example.test', 'fi-sc-text');
})->with(['resource', 'standalone manager', 'resource-backed manager']);

it('keeps non-authenticatable relationship options to escaped bold keys and titles even when email and avatar exist', function (): void {
    RelationshipLabelUser::create(['id' => 'contact', 'name' => '<em>Contact</em>', 'email' => 'private@example.test', 'profile_photo_url' => '/avatars/contact.svg']);
    $field = relationshipFilterLabelSelect(relationshipFilterLabelOperator(relationship: 'contact'));
    $labels = relationshipFilterLabelVariants($field, ['contact'], 'CONTACT');

    expect($labels['contact'])->toBe('<b>#contact</b> - &lt;em&gt;Contact&lt;/em&gt;');
});

it('escapes malicious keys titles and emails in search selected and preloaded relationship filter labels', function (): void {
    $key = 'key<svg onload="alert(1)">';
    $name = 'Name <script>alert(2)</script>';
    $email = '<img src=x onerror="alert(3)">@example.test';
    RelationshipLabelUser::create(['id' => $key, 'name' => $name, 'email' => $email, 'profile_photo_url' => '/avatar.svg?label=" onerror="alert(4)']);
    $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), [$key], 'NAME');
    $dom = relationshipFilterLabelDom($labels[$key]);

    expect($dom->query('//b')->item(0)->textContent)->toBe('#' . $key)
        ->and($dom->query('//span[@class="fi-sc-text"]')->item(0)->textContent)->toBe($email)
        ->and($dom->query('//body')->item(0)->textContent)->toContain($name)
        ->and($dom->query('//script | //svg | //em | //*[@onerror or @onload]')->length)->toBe(0)
        ->and($dom->query('//img')->length)->toBe(1);
});

it('omits protected email from labels and SQL searches without inventing an avatar fallback', function (string $userClass): void {
    RelationshipLabelUser::create(['id' => 'private-user', 'name' => 'Private user', 'email' => 'secret@example.test', 'profile_photo_url' => 'javascript:alert(1)']);
    RelationshipFilterLabelRecord::$ownerModel = $userClass;
    $field = relationshipFilterLabelSelect();
    $labels = relationshipFilterLabelVariants($field, ['private-user'], 'PRIVATE');

    expect($labels['private-user'])->toBe('<b>#private-user</b> - Private user')
        ->and($field->getSearchColumns())->not->toContain('email')
        ->and($field->getSearchResults('secret@example.test'))->toBe([])
        ->and(RelationshipLabelUser::$avatarCalls)->toBe(0);
})->with([
    'hidden email' => [RelationshipLabelHiddenEmailUser::class],
    'nonvisible email' => [RelationshipLabelNonvisibleEmailUser::class],
    'hashed email' => [RelationshipLabelHashedEmailUser::class],
    'encrypted email' => [RelationshipLabelEncryptedEmailUser::class],
]);

it('omits unsafe or absent avatars from every relationship filter label path', function (?string $url): void {
    RelationshipLabelUser::create(['id' => 'unsafe-avatar', 'name' => 'Unsafe avatar', 'profile_photo_url' => $url]);
    $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), ['unsafe-avatar'], 'UNSAFE');

    expect($labels['unsafe-avatar'])->toBe('<b>#unsafe-avatar</b> - Unsafe avatar')
        ->and(RelationshipLabelUser::$avatarCalls)->toBe(0);
})->with([null, '', 'javascript:alert(1)', 'data:image/svg+xml,<svg onload="alert(1)"></svg>', 'file:///etc/passwd']);

it('honors global UserColumn avatar configuration in resource and manager filter labels without leaking it', function (string $surface): void {
    RelationshipLabelUser::create(['id' => 'configured', 'name' => 'Configured', 'profile_photo_url' => '/avatars/default.svg', 'portrait_url' => '/portraits/custom.svg']);
    UserColumn::configureUsing(
        fn (UserColumn $column) => $column->avatarAttribute('portrait_url'),
        during: function () use ($surface): void {
            $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(relationshipFilterLabelOperator(relationshipFilterLabelTable($surface))), ['configured'], 'CONFIGURED');
            $dom = relationshipFilterLabelDom($labels['configured']);
            expect($dom->query('//img')->item(0)->getAttribute('src'))->toBe('/portraits/custom.svg');
        },
    );

    $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), ['configured'], 'CONFIGURED');
    expect(relationshipFilterLabelDom($labels['configured'])->query('//img')->item(0)->getAttribute('src'))->toBe('/avatars/default.svg');
})->with(['resource', 'standalone manager', 'resource-backed manager']);

it('does not fall back to the default avatar when the configured attribute is missing or blank', function (string $attribute): void {
    RelationshipLabelUser::create(['id' => 'blank-avatar', 'name' => 'Blank avatar', 'profile_photo_url' => '/avatars/not-a-fallback.svg']);
    UserColumn::configureUsing(
        fn (UserColumn $column) => $column->avatarAttribute($attribute),
        during: function (): void {
            $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), ['blank-avatar'], 'BLANK');
            expect($labels['blank-avatar'])->toBe('<b>#blank-avatar</b> - Blank avatar');
        },
    );
})->with(['portrait_url', 'missing_avatar']);

it('renders blank and zero titles without dangling separators or lost scalar values', function (?string $title, string $expected): void {
    RelationshipLabelUser::create(['id' => 'scalar', 'name' => $title]);
    $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), ['scalar'], 'SCALAR');

    expect($labels['scalar'])->toBe($expected);
})->with([
    'null' => [null, '<b>#scalar</b>'],
    'blank' => ['', '<b>#scalar</b>'],
    'zero' => ['0', '<b>#scalar</b> - 0'],
]);

it('does not duplicate the primary key when it is the guessed relationship title', function (): void {
    RelationshipFilterLabelRecord::$ownerModel = RelationshipLabelKeyUser::class;
    RelationshipLabelKeyUser::create(['id' => 'key-only']);
    $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), ['key-only'], 'KEY-ONLY');

    expect($labels['key-only'])->toBe('<b>#key-only</b>');
});

it('respects partial selects and missing optional user attributes in strict model mode', function (string $scenario): void {
    RelationshipLabelUser::create(['id' => 'partial', 'name' => 'Partial', 'email' => 'not-selected@example.test', 'profile_photo_url' => '/avatars/not-selected.svg']);
    if ($scenario === 'partial select') {
        RelationshipFilterLabelRecord::$ownerModel = RelationshipLabelPartialUser::class;
    } else {
        Schema::table('relationship_label_contacts', fn ($table) => $table->dropColumn(['email', 'profile_photo_url', 'portrait_url']));
    }
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $labels = relationshipFilterLabelVariants(relationshipFilterLabelSelect(), ['partial'], 'PARTIAL');
        expect($labels['partial'])->toBe('<b>#partial</b> - Partial');
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
})->with(['partial select', 'missing optional columns']);

it('retains declared relationship restrictions in searches selected labels preloads summaries and filtering', function (): void {
    RelationshipLabelUser::create(['id' => 'allowed', 'name' => 'Allowed user', 'title' => 'Allowed']);
    RelationshipLabelUser::create(['id' => 'outside', 'name' => 'Outside user', 'title' => 'Outside']);
    $record = RelationshipFilterLabelScopedRecord::create(['owner_id' => 'allowed', 'parent_id' => 1]);
    RelationshipFilterLabelScopedRecord::create(['owner_id' => 'outside', 'parent_id' => 1]);
    RelationshipFilterLabelScopedRecord::create(['owner_id' => 'allowed', 'parent_id' => 2]);
    $operator = relationshipFilterLabelOperator(model: RelationshipFilterLabelScopedRecord::class);
    $field = relationshipFilterLabelSelect($operator);
    $field->state(['allowed', 'outside', 'nonexistent']);
    $expected = ['allowed' => '<b>#allowed</b> - Allowed user'];

    expect($field->getOptionLabels())->toBe($expected)
        ->and($field->getSearchResults('USER'))->toBe($expected)
        ->and($field->preload()->getOptions())->toBe($expected);
    $operator->settings(['value' => ['allowed', 'outside']])->inverse(false);
    expect($operator->getSummary())->toContain('Allowed user')->not->toContain('Outside user', '<b>')
        ->and($operator->apply(RelationshipFilterLabelScopedRecord::query()->where('parent_id', 1), 'owner')->pluck('id')->all())->toBe([$record->getKey()]);
    $operator->settings(['value' => ['outside']]);
    expect($operator->apply(RelationshipFilterLabelScopedRecord::query(), 'owner')->exists())->toBeFalse();
    $operator->settings(['value' => [['tampered' => 'allowed']]]);
    expect($operator->apply(RelationshipFilterLabelScopedRecord::query()->whereKey($record->getKey()), 'owner')->pluck('id')->all())->toBe([$record->getKey()]);
});

it('hydrates base record keys titles emails and avatars without dropping joined global scopes', function (): void {
    RelationshipFilterLabelRecord::$ownerModel = RelationshipLabelJoinedUser::class;
    RelationshipLabelUser::create(['id' => 'source', 'name' => 'Source user', 'email' => 'source@example.test', 'profile_photo_url' => '/avatars/source.svg']);
    RelationshipLabelUser::create(['id' => 'excluded', 'name' => 'Excluded user']);
    foreach (['source' => true, 'excluded' => false] as $id => $active) {
        DB::table('relationship_label_flags')->insert(['id' => 'flag-' . $id, 'contact_id' => $id, 'name' => 'Joined user', 'email' => 'joined@example.test', 'profile_photo_url' => '/avatars/joined.svg', 'active' => $active]);
    }
    $operator = relationshipFilterLabelOperator();
    $field = relationshipFilterLabelSelect($operator);
    $labels = relationshipFilterLabelVariants($field, ['source'], 'SOURCE@EXAMPLE.TEST');
    $dom = relationshipFilterLabelDom($labels['source']);

    expect($dom->query('//b')->item(0)->textContent)->toBe('#source')
        ->and($dom->query('//body')->item(0)->textContent)->toContain('Source user', 'source@example.test')
        ->and($dom->query('//img')->item(0)->getAttribute('src'))->toBe('/avatars/source.svg')
        ->and($labels['source'])->not->toContain('Joined user', 'joined@example.test')
        ->and($field->getSearchResults('EXCLUDED'))->toBe([]);
    $field->state(['excluded']);
    expect($field->getOptionLabels())->toBe([]);
    $operator->settings(['value' => ['source', 'excluded']])->inverse(false);
    expect($operator->getSummary())->toContain('Source user')->not->toContain('Excluded user', '<b>');
});

it('leaves explicitly configured native relationship operators and unrelated selects plain', function (): void {
    $operator = IsRelatedToOperator::make()->titleAttribute('name')->searchable()->multiple();
    $table = Table::make(new RelationshipFilterLabelFormHost())->query(RelationshipFilterLabelRecord::query());
    $table->filters([QueryBuilder::make()->constraints([
        RelationshipConstraint::make('owner')->selectable($operator),
    ])]);
    $constraint = $table->getFilter('queryBuilder')->getConstraints()['owner'];
    $field = $operator->constraint($constraint)->getFormSchema()[0];

    expect($field->isHtmlAllowed())->toBeFalse()
        ->and(Select::make('unrelated')->isHtmlAllowed())->toBeFalse();
});
