<?php

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchAccessorContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\CustomRelationshipSearchFormGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchJoinedContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchKeyContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipSearchSecondaryContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldTitleIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Owner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Models\Product;


require_once __DIR__ . '/../Fixtures/RelationshipSearchFixtures.php';
require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';

beforeEach(function (): void {
    RelationshipSearchFixtures::create();
    UserFieldFixtures::create();
});

afterEach(function (): void {
    UserFieldFixtures::drop();
    RelationshipSearchFixtures::drop();
});

function mountRelationshipSearchSelect(Select $field, Model $model): Select
{
    FilamentSchema::make(new UserFieldListRecords())
        ->model($model)
        ->statePath('userFieldData')
        ->components([$field])
        ->getFlatComponents(withHidden: true);

    return $field;
}

function relationshipSearchSelect(Model $model, string $foreignKey = 'owner_id'): Select
{
    return mountRelationshipSearchSelect(FormGenerator::makeForModel($model, relationManagerView: true)[$foreignKey], $model);
}

function relationshipSearchLabel(Model $record, string $title = 'name'): string
{
    $label = '<b>#' . e((string) $record->getKey()) . '</b>';

    return $title === $record->getKeyName() ? $label : $label . ' - ' . e((string) $record->getAttribute($title));
}

it('searches generic relationships by name title email or partial id without changing labels or searching unrelated columns', function (): void {
    $titleMatch = RelationshipSearchContact::create(['id' => 73104, 'name' => 'Needle in title', 'email' => 'first@example.test']);
    $emailMatch = RelationshipSearchContact::create(['id' => 85206, 'name' => 'Email match', 'email' => 'before.NeEdLe.after@example.test']);
    $alternateTitleMatch = RelationshipSearchContact::create(['id' => 96308, 'name' => 'Unrelated', 'title' => 'alternate-title-only', 'notes' => 'notes-only']);
    $model = new RelationshipSearchRecord(['owner_id' => $emailMatch->id]);
    $field = relationshipSearchSelect($model);

    expect(RelationshipGuesser::guessSearchColumns($model, 'owner', 'name'))->toBe(['name', 'id', 'title', 'email'])
        ->and($field::class)->toBe(Select::class)
        ->and($field->getName())->toBe('owner_id')
        ->and($field->getRelationshipName())->toBe('owner')
        ->and($field->getRelationshipTitleAttribute())->toBe('name')
        ->and($field->getSearchColumns())->toBe(['name', 'id', 'title', 'email'])
        ->and($field->isRequired())->toBeFalse()
        ->and($field->isSearchable())->toBeTrue()
        ->and($field->isSearchForcedCaseInsensitive())->toBeTrue()
        ->and($field->getSearchResults('nEeDlE'))->toBe([$emailMatch->id => relationshipSearchLabel($emailMatch), $titleMatch->id => relationshipSearchLabel($titleMatch)])
        ->and($field->getSearchResults('310'))->toBe([$titleMatch->id => relationshipSearchLabel($titleMatch)])
        ->and($field->getSearchResults('AlTeRnAtE-tItLe'))->toBe([$alternateTitleMatch->id => relationshipSearchLabel($alternateTitleMatch)])
        ->and($field->getSearchResults('notes-only'))->toBe([]);

    $field->state($emailMatch->id);
    expect($field->getState())->toBe($emailMatch->id)
        ->and($field->getOptionLabel())->toBe(relationshipSearchLabel($emailMatch));
});

it('deduplicates conventional primary-key names across all guessed search columns', function (string $keyName): void {
    $model = new class extends RelationshipSearchRecord
    {
        public string $relatedKeyName = 'id';

        public function owner(): BelongsTo
        {
            $relationship = parent::owner();
            $relationship->getRelated()->setKeyName($this->relatedKeyName);

            return $relationship;
        }
    };
    $model->relatedKeyName = $keyName;
    $columns = relationshipSearchSelect($model)->getSearchColumns();

    expect($columns)->toBe(array_values(array_unique(['name', $keyName, 'id', 'title', 'email'])))
        ->and($columns)->toHaveCount(4)
        ->and(array_count_values($columns)[$keyName])->toBe(1);
})->with(['id', 'name', 'title', 'email']);

it('searches native user selects by email title partial id and mixed-case name with rich labels', function (): void {
    Schema::table('user_field_identities', function (Blueprint $table): void {
        $table->string('email')->nullable();
        $table->string('title')->nullable();
    });
    $identity = UserFieldIdentity::create(['id' => 73104, 'name' => 'Alice <b>Identity</b>', 'email' => 'before.MailNeedle.after@example.test', 'title' => 'Avatar title']);
    UserFieldIdentity::create(['id' => 85206, 'name' => 'Other identity', 'email' => 'other@example.test']);
    $model = new UserFieldRecord(['author_id' => $identity->id]);
    $field = relationshipSearchSelect($model, 'author_id');

    expect($field::class)->toBe(Select::class)
        ->and($field->getSearchColumns())->toBe(['name', 'id', 'title', 'email'])
        ->and($field->getRelationshipTitleAttribute())->toBe('name')
        ->and($field->isHtmlAllowed())->toBeTrue()
        ->and($field->isNative())->toBeFalse()
        ->and($field->isSearchForcedCaseInsensitive())->toBeTrue();

    foreach (['mAiLnEeDlE', 'aVaTaR tItLe', '310', 'aLiCe'] as $search) {
        $results = $field->getSearchResults($search);
        expect(array_keys($results))->toBe([$identity->id])
            ->and($results[$identity->id])->toBe('<span style="display:block">' . relationshipSearchLabel($identity)
                . '</span> <span class="fi-sc-text" style="display:block">' . e((string) $identity->email) . '</span>');
    }

    $field->state($identity->id);
    expect($field->getState())->toBe($identity->id)
        ->and((string) $field->getOptionLabel())->toBe((string) $field->getSearchResults('mAiLnEeDlE')[$identity->id]);
});

it('searches a relationship without an email column without issuing invalid SQL', function (): void {
    $owner = Owner::create(['id' => 73104, 'name' => 'Owner without email']);
    $model = new Product();
    $field = relationshipSearchSelect($model);

    expect(RelationshipGuesser::guessSearchColumns($model, 'owner', 'name'))->toBe(['name', 'id'])
        ->and($field->getSearchColumns())->toBe(['name', 'id'])
        ->and($field->getSearchResults('310'))->toBe([$owner->id => relationshipSearchLabel($owner)])
        ->and($field->getSearchResults('wItHoUt'))->toBe([$owner->id => relationshipSearchLabel($owner)]);
});

it('retains the title fallback for user models without a name column', function (): void {
    $identity = UserFieldTitleIdentity::create(['id' => 73104, 'title' => 'Fallback title']);
    $model = new UserFieldRecord();
    $field = relationshipSearchSelect($model, 'title_user_id');

    expect($field::class)->toBe(Select::class)
        ->and($field->getRelationshipTitleAttribute())->toBe('title')
        ->and(RelationshipGuesser::guessSearchColumns($model, 'titleUser', 'title'))->toBe(['title', 'id'])
        ->and($field->getSearchColumns())->toBe(['title', 'id'])
        ->and($field->getSearchResults('lLbAcK'))->toBe([$identity->id => relationshipSearchLabel($identity, 'title')])
        ->and($field->getSearchResults('310'))->toBe([$identity->id => relationshipSearchLabel($identity, 'title')]);
});

it('searches custom string primary keys without changing option values', function (string $key, string $fragment): void {
    Schema::create('relationship_search_key_contacts', function (Blueprint $table): void {
        $table->string('contact_key')->primary();
        $table->unsignedBigInteger('id');
        $table->string('name');
        $table->string('title');
        $table->string('email');
    });
    $contact = RelationshipSearchKeyContact::create(['contact_key' => $key, 'id' => 73104, 'name' => 'Custom key label', 'title' => 'Custom alternate title', 'email' => 'custom-email@example.test']);
    $model = new RelationshipSearchRecord(['owner_id' => $key]);
    $model->ownerModel = RelationshipSearchKeyContact::class;
    $field = relationshipSearchSelect($model);

    expect(RelationshipGuesser::guessSearchColumns($model, 'owner', 'name'))->toBe(['name', 'contact_key', 'id', 'title', 'email'])
        ->and($field->getSearchColumns())->toBe(['name', 'contact_key', 'id', 'title', 'email'])
        ->and($field->getSearchResults('310'))->toBe([$key => relationshipSearchLabel($contact)])
        ->and($field->getSearchResults('AlTeRnAtE tItLe'))->toBe([$key => relationshipSearchLabel($contact)])
        ->and($field->getSearchResults($fragment))->toBe([$key => relationshipSearchLabel($contact)])
        ->and($field->getSearchResults('CuStOm-EmAiL'))->toBe([$key => relationshipSearchLabel($contact)]);

    $field->state($key);
    expect($field->getState())->toBe($key)
        ->and($field->getOptionLabel())->toBe(relationshipSearchLabel($contact));
})->with([
    'string' => ['contact-West-72', 't-wEsT-'],
    'UUID' => ['d46b5c80-3d8a-4f78-b73b-c8e945018cc2', 'A-4F78-B'],
]);

it('deduplicates a key-only title fallback and preserves it even when hidden', function (): void {
    Schema::create('relationship_search_key_contacts', fn (Blueprint $table) => $table->string('contact_key')->primary());
    $contact = RelationshipSearchKeyContact::create(['contact_key' => 'key-only-West-72']);
    $model = new RelationshipSearchRecord();
    $model->ownerModel = RelationshipSearchKeyContact::class;
    $model->ownerHidden = ['contact_key'];
    $field = relationshipSearchSelect($model);

    expect($field->getRelationshipTitleAttribute())->toBe('contact_key')
        ->and(RelationshipGuesser::guessSearchColumns($model, 'owner', 'contact_key'))->toBe(['contact_key'])
        ->and($field->getSearchColumns())->toBe(['contact_key'])
        ->and($field->getSearchResults('oNlY-wEsT'))->toBe([$contact->getKey() => '<b>#key-only-West-72</b>']);
});

it('keeps a custom belongs-to owner key as the option value while searching the model primary key', function (): void {
    $contact = RelationshipSearchContact::create(['id' => 73104, 'name' => 'Owner-key label', 'email' => 'owner-email@example.test', 'handle' => 'external-owner-key']);
    $model = new RelationshipSearchRecord(['owner_id' => $contact->handle]);
    $model->ownerKey = 'handle';
    $field = relationshipSearchSelect($model);

    expect($field->getSearchColumns())->toBe(['name', 'id', 'title', 'email'])
        ->and($field->getSearchResults('310'))->toBe([$contact->handle => relationshipSearchLabel($contact)])
        ->and($field->getSearchResults('OwNeR-EmAiL'))->toBe([$contact->handle => relationshipSearchLabel($contact)])
        ->and($field->getSearchResults('external-owner-key'))->toBe([]);

    $field->state($contact->handle);
    expect($field->getState())->toBe($contact->handle)
        ->and($field->getOptionLabel())->toBe(relationshipSearchLabel($contact));
});

it('uses the actual related connection and native table prefix for search', function (bool $explicitConnection, bool $withEmail): void {
    if ($withEmail) {
        Schema::table('relationship_search_contacts', fn (Blueprint $table) => $table->dropColumn('email'));
    }
    RelationshipSearchContact::create(['id' => 73104, 'name' => 'Default connection decoy']);
    Schema::connection('secondary')->create('relationship_search_contacts', function (Blueprint $table) use ($withEmail): void {
        $table->id();
        $table->string('title');
        if ($withEmail) {
            $table->string('email');
        }
    });
    Schema::connection('secondary')->create('relationship_search_records', function (Blueprint $table): void {
        $table->id();
        $table->string('owner_id')->nullable();
    });
    $model = new RelationshipSearchRecord();
    if ($explicitConnection) {
        $model->ownerModel = RelationshipSearchSecondaryContact::class;
    } else {
        $model->setConnection('secondary');
    }
    $related = $model->owner()->getRelated();
    $attributes = ['id' => 73104, 'title' => 'Secondary title'];
    if ($withEmail) {
        $attributes['email'] = 'route-to-secondary@example.test';
    }
    $related->newQuery()->create($attributes);
    $field = relationshipSearchSelect($model);
    $columns = $withEmail ? ['title', 'id', 'email'] : ['title', 'id'];

    expect($related->getConnectionName())->toBe('secondary')
        ->and($related->getConnection()->getTablePrefix())->toBe('secondary_')
        ->and(RelationshipGuesser::guessSearchColumns($model, 'owner', 'title'))->toBe($columns)
        ->and($field->getSearchColumns())->toBe($columns)
        ->and($field->getRelationshipTitleAttribute())->toBe('title')
        ->and($field->getSearchResults('310'))->toBe([73104 => '<b>#73104</b> - Secondary title'])
        ->and($field->getSearchResults('oNdArY tIt'))->toBe([73104 => '<b>#73104</b> - Secondary title'])
        ->and($field->getSearchResults('rOuTe-To-SeCoNdArY'))->toBe($withEmail ? [73104 => '<b>#73104</b> - Secondary title'] : []);
})->with([
    'inherited connection with email absent on default' => [false, true],
    'inherited connection without email present on default' => [false, false],
    'explicit related connection with email absent on default' => [true, true],
]);

it('qualifies joined global-scope searches without searching joined-only columns on either connection', function (string $connection, bool $withEmail): void {
    if ($connection === 'secondary') {
        RelationshipSearchFixtures::create($connection);
    }
    RelationshipSearchFixtures::createContactFlags($connection);
    if (! $withEmail) {
        Schema::connection($connection)->table('relationship_search_contacts', fn (Blueprint $table) => $table->dropColumn('email'));
    }
    $attributes = ['name' => 'Joined label', 'title' => 'alternate-needle', 'notes' => 'notes-only'];
    if ($withEmail) {
        $attributes['email'] = 'mail-needle@example.test';
    }
    $contact = RelationshipSearchContact::on($connection)->create(['id' => 73104, ...$attributes]);
    $scopedOut = RelationshipSearchContact::on($connection)->create(['id' => 85206, ...$attributes]);
    DB::connection($connection)->table('relationship_search_contact_flags')->insert([
        ['id' => 96308, 'contact_id' => $contact->id, 'active' => true, 'title' => 'join-only-needle', 'email' => 'mail-needle@example.test'],
        ['id' => 97409, 'contact_id' => $scopedOut->id, 'active' => false, 'title' => 'join-only-needle', 'email' => 'mail-needle@example.test'],
    ]);
    $model = (new RelationshipSearchRecord(['owner_id' => $contact->id]))->setConnection($connection);
    $model->ownerModel = RelationshipSearchJoinedContact::class;
    $field = relationshipSearchSelect($model);
    $related = $model->owner()->getRelated();
    $database = $related->getConnection();
    $columns = $withEmail ? ['name', 'id', 'title', 'email'] : ['name', 'id', 'title'];
    $expected = array_map(static fn (string $column): string => $database->getDriverName() === 'pgsql'
        ? $related->qualifyColumn($column)
        : $database->getQueryGrammar()->wrap($related->qualifyColumn($column)), $columns);
    $result = [$contact->id => relationshipSearchLabel($contact)];

    expect($database->getTablePrefix())->toBe($connection === 'secondary' ? 'secondary_' : '')
        ->and($field::class)->toBe(Select::class)
        ->and($field->getSearchColumns())->toBe($expected)
        ->and($field->getRelationshipTitleAttribute())->toBe('name')
        ->and($field->getSearchResults('jOiNeD lAbEl'))->toBe($result)
        ->and($field->getSearchResults('AlTeRnAtE-NeEdLe'))->toBe($result)
        ->and($field->getSearchResults('310'))->toBe($result)
        ->and($field->getSearchResults('mAiL-NeEdLe'))->toBe($withEmail ? $result : [])
        ->and($field->getSearchResults('join-only-needle'))->toBe([])
        ->and($field->getSearchResults('630'))->toBe([])
        ->and($field->getSearchResults('notes-only'))->toBe([]);
    $field->state($contact->id);
    expect($field->getOptionLabel())->toBe(relationshipSearchLabel($contact));
})->with(['unprefixed' => 'testing', 'prefixed' => 'secondary'])->with(['with email' => true, 'missing email' => false]);

it('preserves candidate exclusions and the existing label search when qualifying joined queries', function (string $connection, string $cast): void {
    if ($connection === 'secondary') {
        RelationshipSearchFixtures::create($connection);
    }
    RelationshipSearchFixtures::createContactFlags($connection);
    DB::connection($connection)->table('relationship_search_contacts')->insert(['id' => 73104, 'name' => 'Visible label', 'title' => 'visible-excluded-needle', 'email' => 'cast-excluded-needle']);
    DB::connection($connection)->table('relationship_search_contact_flags')->insert(['id' => 96308, 'contact_id' => 73104]);
    $model = (new RelationshipSearchRecord())->setConnection($connection);
    $model->ownerModel = RelationshipSearchJoinedContact::class;
    $model->ownerHidden = ['name', 'id'];
    $model->ownerVisible = ['name', 'id', 'email'];
    $model->ownerCasts = ['email' => $cast];
    $field = relationshipSearchSelect($model);

    expect($field->getSearchColumns())->toHaveCount(1)
        ->and($field->getSearchResults('ViSiBlE lAbEl'))->toBe([73104 => '<b>#73104</b> - Visible label'])
        ->and($field->getSearchResults('310'))->toBe([])
        ->and($field->getSearchResults('visible-excluded-needle'))->toBe([])
        ->and($field->getSearchResults('cast-excluded-needle'))->toBe([]);
})->with(['unprefixed' => 'testing', 'prefixed' => 'secondary'])->with(['encrypted:array', 'hashed']);

it('never turns accessor-only keys or email into SQL search columns', function (): void {
    Schema::table('relationship_search_contacts', fn (Blueprint $table) => $table->dropColumn('email'));
    $contact = RelationshipSearchContact::create(['id' => 73104, 'name' => 'Visible title']);
    $model = new RelationshipSearchRecord();
    $model->ownerModel = RelationshipSearchAccessorContact::class;
    $model->ownerKey = 'id';
    $field = relationshipSearchSelect($model);

    expect(RelationshipGuesser::guessSearchColumns($model, 'owner', 'name'))->toBe(['name', 'id', 'title'])
        ->and($field->getSearchColumns())->toBe(['name', 'id', 'title'])
        ->and($field->getSearchResults('310'))->toBe([$contact->id => '<b>#accessor-key-needle</b> - Visible title'])
        ->and($field->getSearchResults('iSiBlE'))->toBe([$contact->id => '<b>#accessor-key-needle</b> - Visible title'])
        ->and($field->getSearchResults('accessor-key-needle'))->toBe([])
        ->and($field->getSearchResults('accessor-email-needle'))->toBe([]);
});

it('filters unsafe new candidates but always retains the existing title search', function (array $hidden, array $visible, array $casts, array $columns): void {
    // Raw values make accidental SQL searches observable even for cast-excluded candidates.
    DB::table('relationship_search_contacts')->insert(['id' => 73104, 'name' => 'Visible title', 'email' => 'private-search@example.test']);
    $model = new RelationshipSearchRecord();
    $model->ownerHidden = $hidden;
    $model->ownerVisible = $visible;
    $model->ownerCasts = $casts;
    $field = relationshipSearchSelect($model);
    $result = [73104 => '<b>#73104</b> - Visible title'];

    expect(RelationshipGuesser::guessSearchColumns($model, 'owner', 'name'))->toBe($columns)
        ->and($field->getSearchColumns())->toBe($columns);

    // Encrypted casts on this BIGINT primary key are configuration-only cases:
    // native option mapping must read the key, so these raw IDs cannot be rendered.
    if (in_array($casts['id'] ?? null, ['encrypted', 'encrypted:array'], true)) {
        return;
    }

    expect($field->getSearchResults('iSiBlE'))->toBe($result)
        ->and($field->getSearchResults('310'))->toBe(in_array('id', $columns, true) ? $result : [])
        ->and($field->getSearchResults('PrIvAtE-SeArCh'))->toBe(in_array('email', $columns, true) ? $result : []);
})->with([
    'hidden candidates and existing label' => [['name', 'id', 'title', 'email'], [], [], ['name']],
    'hidden email only' => [['email'], [], [], ['name', 'id', 'title']],
    'hidden key only' => [['id'], [], [], ['name', 'title', 'email']],
    'visible title only' => [[], ['name'], [], ['name']],
    'existing title outside visible allowlist' => [[], ['id', 'email'], [], ['name', 'id', 'email']],
    'encrypted candidates' => [[], [], ['id' => 'encrypted', 'title' => 'encrypted', 'email' => 'encrypted'], ['name']],
    'parameterized encrypted candidates' => [[], [], ['id' => 'encrypted:array', 'title' => 'encrypted:array', 'email' => 'encrypted:array'], ['name']],
    'hashed candidates' => [[], [], ['id' => 'hashed', 'title' => 'hashed', 'email' => 'hashed'], ['name']],
    'encrypted email and title with valid key' => [[], [], ['title' => 'encrypted', 'email' => 'encrypted'], ['name', 'id']],
    'parameterized encrypted email and title with valid key' => [[], [], ['title' => 'encrypted:array', 'email' => 'encrypted:array'], ['name', 'id']],
]);

it('lets an extension replace generated search columns after the parent relationship handler', function (): void {
    $contact = RelationshipSearchContact::create(['id' => 73104, 'name' => 'Extension label', 'email' => 'not-searched@example.test', 'handle' => 'custom-search-handle']);
    $model = new RelationshipSearchRecord();
    $field = mountRelationshipSearchSelect(CustomRelationshipSearchFormGenerator::makeForModel($model, relationManagerView: true)['owner_id'], $model);

    expect($field->getLabel())->toBe('Extension owner')
        ->and($field->getSearchColumns())->toBe(['handle'])
        ->and($field->getSearchResults('SeArCh-HaNdLe'))->toBe([$contact->id => relationshipSearchLabel($contact)])
        ->and($field->getSearchResults('310'))->toBe([])
        ->and($field->getSearchResults('not-searched'))->toBe([]);
});

it('preserves explicit select overwrites and enum dictionary priority ahead of relationship search', function (): void {
    Schema::table('user_field_identities', fn (Blueprint $table) => $table->string('email')->nullable());
    $identity = UserFieldIdentity::create(['name' => 'Do not search this title', 'email' => 'overwrite-search@example.test']);
    $model = new UserFieldRecord();
    $override = Select::make('author_id')->relationship('author', 'name')->searchable(['email'])->forceSearchCaseInsensitive();
    $fields = FormGenerator::makeForModel(
        $model,
        exceptColumns: ['user_id'],
        overwriteColumns: ['user_id' => Select::make('user_id'), 'author_id' => $override],
        enumDictionary: ['user_id' => [1 => 'Excluded'], 'author_id' => [1 => 'Must not replace overwrite'], 'reviewed_by_id' => [1 => 'Dictionary reviewer']],
        relationManagerView: true,
    );
    mountRelationshipSearchSelect($fields['author_id'], $model);

    expect($fields)->not->toHaveKey('user_id')
        ->and($fields['author_id'])->toBe($override)
        ->and($override->getSearchColumns())->toBe(['email'])
        ->and($override->getSearchResults('OvErWrItE-SeArCh'))->toBe([$identity->id => $identity->name])
        ->and($override->getSearchResults('Do not search'))->toBe([])
        ->and($fields['reviewed_by_id']::class)->toBe(Select::class)
        ->and($fields['reviewed_by_id']->hasRelationship())->toBeFalse()
        ->and($fields['reviewed_by_id']->getOptions())->toBe([1 => 'Dictionary reviewer']);
});
