<?php

use Filament\Actions\Enums\ActionStatus;

use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Mounters\RelationManagerMounter;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormAccessorResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormAccessorTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormCustomOwnerRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormDeniedNameResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormDeniedResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormDeniedTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormDuplicateNameResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormHost;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormIntegerKeyResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormIntegerKeyTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormJoinedResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormJoinedTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormKeyResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormKeyTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormMapOnlyTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormNameResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormNameTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormOwner;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormRawRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormRecordResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormSafeResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormSafeTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormScopedResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormScopedTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormSecondaryResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormSecondaryTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormStringIdResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormStringIdTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormTitleResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormTitleTarget;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\MorphFormUnverifiedRecord;

require_once __DIR__ . '/../Fixtures/MorphFormFixtures.php';

beforeEach(function (): void {
    MorphFormFixtures::create();
    MorphFormFixtures::panel();
});

afterEach(function (): void {
    MorphFormFixtures::drop();
});

function morphFormFields(?Model $record = null, array $except = [], array $overwrites = [], array $dictionary = []): array
{
    return FormGenerator::makeForModel(
        $record ?? new MorphFormRecord(),
        exceptColumns: $except,
        overwriteColumns: $overwrites,
        enumDictionary: $dictionary,
        relationManagerView: true,
    );
}

function morphFormSchema(?Model $record = null, ?array $state = null): array
{
    $record ??= new MorphFormRecord();
    $fields = morphFormFields($record);
    expect($fields)->toHaveKey('subject');
    expect($fields['subject'])->toBeInstanceOf(MorphToSelect::class);
    $host = new MorphFormHost();
    $schema = FilamentSchema::make($host)->model($record)->statePath('morphFormData')->components([$fields['subject']]);
    $schema->fill($state ?? $record->attributesToArray());

    return [$host, $schema, $fields['subject']];
}

function morphFormSelects(MorphToSelect $field): array
{
    $children = $field->getChildSchema()->getComponents(withHidden: true);
    expect($children)->toHaveCount(2);
    expect($children[0]::class)->toBe(Select::class);
    expect($children[1]::class)->toBe(Select::class);

    return $children;
}

function assertInvalidMorphFormState(FilamentSchema $schema, string $column): void
{
    try {
        $schema->getState();
        test()->fail('Expected native select validation to reject ' . $column);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('morphFormData.' . $column);
    }
}

it('groups a verified pair into a native relationship wrapper with raw child attributes and no preload', function (): void {
    $fields = FormGenerator::make(MorphFormRecord::class, relationManagerView: true);
    expect(array_keys($fields))->toEqualCanonicalizing(['id', 'name', 'subject']);
    [$host, $schema, $field] = morphFormSchema(state: ['subject_type' => MorphFormNameTarget::class]);
    [$type, $key] = morphFormSelects($field);

    expect($field::class)->toBe(MorphToSelect::class)
        ->and($field->getName())->toBe('subject')
        ->and($field->getTypes()[MorphFormNameTarget::class]::class)->toBe(Type::class)
        ->and($type->getName())->toBe('subject_type')
        ->and($type->getStatePath())->toBe('morphFormData.subject_type')
        ->and($type->isLive())->toBeTrue()
        ->and($key->getName())->toBe('subject_id')
        ->and($key->getStatePath())->toBe('morphFormData.subject_id')
        ->and($key->isSearchable())->toBeTrue()
        ->and($field->isPreloaded())->toBeFalse()
        ->and($key->isPreloaded())->toBeFalse();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $options = $key->getOptions();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($options)->toBe([])->and($queries)->toBe([]);
});

it('only offers accessible active-panel resources and deduplicates models without importing morph-map-only types', function (): void {
    Relation::morphMap(['map-only' => MorphFormMapOnlyTarget::class, 'denied' => MorphFormDeniedTarget::class]);
    MorphFormFixtures::panel([
        MorphFormDeniedResource::class,
        MorphFormDeniedNameResource::class,
        MorphFormNameResource::class,
        MorphFormDuplicateNameResource::class,
        MorphFormTitleResource::class,
    ]);
    [, , $field] = morphFormSchema();

    expect(array_keys($field->getTypes()))->toEqualCanonicalizing([MorphFormNameTarget::class, MorphFormTitleTarget::class])
        ->and($field->getTypes())->toHaveCount(2);
});

it('resolves target types afresh when the active panel changes', function (): void {
    [, , $first] = morphFormSchema();
    expect(array_keys($first->getTypes()))->toBe([MorphFormNameTarget::class]);

    MorphFormFixtures::panel([MorphFormTitleResource::class]);
    [, , $second] = morphFormSchema();
    expect(array_keys($second->getTypes()))->toBe([MorphFormTitleTarget::class]);
});

it('hydrates dehydrates and persists the raw morph alias or FQCN without nesting under the wrapper', function (bool $mapped): void {
    if ($mapped) {
        Relation::morphMap(['named' => MorphFormNameTarget::class]);
    }
    $alias = $mapped ? 'named' : MorphFormNameTarget::class;
    $target = MorphFormNameTarget::create(['name' => 'Selected name']);
    [$host, $schema, $field] = morphFormSchema(state: ['subject_type' => $alias, 'subject_id' => $target->id]);
    [$type, $key] = morphFormSelects($field);
    $state = $schema->getState();

    expect(array_keys($type->getOptions()))->toBe([$alias])
        ->and($type->getState())->toBe($alias)
        ->and($key->getOptionLabel())->toBe('Selected name')
        ->and($state)->toHaveCount(2)->toHaveKeys(['subject_type', 'subject_id'])
        ->not->toHaveKeys(['subject', 'subject.name'])
        ->and($state['subject_type'])->toBe($alias)
        ->and((int) $state['subject_id'])->toBe($target->id);

    $record = MorphFormRecord::create($state)->fresh();
    [$editHost, $editSchema, $editField] = morphFormSchema($record);
    [, $editKey] = morphFormSelects($editField);
    expect($record->subject->is($target))->toBeTrue()
        ->and($editHost->morphFormData['subject_type'])->toBe($alias)
        ->and((int) $editHost->morphFormData['subject_id'])->toBe($target->id)
        ->and($editKey->getOptionLabel())->toBe('Selected name')
        ->and($editSchema->getState())->toEqual($state);
})->with(['FQCN' => false, 'morph-map alias' => true]);

it('skips unmapped resource models under an enforced morph map and falls back when none remain', function (): void {
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormTitleResource::class]);
    Relation::enforceMorphMap(['named' => MorphFormNameTarget::class], merge: false);
    [, , $field] = morphFormSchema();
    expect(array_keys($field->getTypes()))->toBe(['named']);

    Relation::enforceMorphMap(['map-only' => MorphFormMapOnlyTarget::class], merge: false);
    $fields = morphFormFields();
    expect($fields)->toHaveKeys(['subject_type', 'subject_id'])->not->toHaveKey('subject');
});

it('only accepts int-key targets for a numeric foreign key including nonstandard integer primary keys', function (): void {
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormKeyResource::class, MorphFormIntegerKeyResource::class, MorphFormStringIdResource::class]);
    $target = MorphFormIntegerKeyTarget::create(['target_number' => 73104]);
    [, $schema, $field] = morphFormSchema(state: ['subject_type' => MorphFormIntegerKeyTarget::class, 'subject_id' => $target->getKey()]);
    [, $key] = morphFormSelects($field);

    expect(array_keys($field->getTypes()))->toEqualCanonicalizing([MorphFormNameTarget::class, MorphFormIntegerKeyTarget::class])
        ->and($key->getSearchResults('310'))->toBe([73104 => '73104'])
        ->and($key->getOptionLabel())->toBe('73104');
    $record = MorphFormRecord::create($schema->getState());
    expect($record->fresh()->subject->is($target))->toBeTrue();
});

it('permits string and UUID custom primary keys with a string foreign key even on a hydrated source', function (string $value): void {
    MorphFormFixtures::createRecords(stringKey: true);
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormKeyResource::class, MorphFormStringIdResource::class]);
    $target = MorphFormKeyTarget::create(['target_key' => $value, 'name' => 'Custom key label']);
    $record = MorphFormRecord::create(['subject_type' => MorphFormKeyTarget::class, 'subject_id' => $value])->fresh();
    [$host, $schema, $field] = morphFormSchema($record);
    [, $key] = morphFormSelects($field);

    expect(array_keys($field->getTypes()))->toEqualCanonicalizing([MorphFormNameTarget::class, MorphFormKeyTarget::class, MorphFormStringIdTarget::class])
        ->and($host->morphFormData['subject_id'])->toBe($value)
        ->and($key->getOptionLabel())->toBe('Custom key label')
        ->and($key->getSearchResults(substr($value, 3, 8)))->toBe([$value => 'Custom key label'])
        ->and($schema->getState()['subject_id'])->toBe($value);
    $record->fill($schema->getState())->save();
    expect($record->fresh()->subject->is($target))->toBeTrue();
})->with(['string' => 'target-West-72', 'UUID' => 'd46b5c80-3d8a-4f78-b73b-c8e945018cc2']);

it('uses the target explicit connection or inherits the source runtime connection including native table prefixes', function (bool $explicit): void {
    MorphFormFixtures::createTargets('secondary');
    MorphFormFixtures::createRecords(connection: 'secondary');
    Schema::connection('secondary')->table('morph_form_names', fn (Blueprint $table) => $table->dropColumn(['name', 'email']));
    MorphFormNameTarget::create(['id' => 73104, 'name' => 'Default decoy']);
    $targetClass = $explicit ? MorphFormSecondaryTarget::class : MorphFormNameTarget::class;
    $resource = $explicit ? MorphFormSecondaryResource::class : MorphFormNameResource::class;
    $target = (new $targetClass)->setConnection('secondary')->newQuery()->create(['id' => 73104, 'title' => 'Secondary label']);
    $record = new MorphFormRecord();
    if (! $explicit) {
        $record->setConnection('secondary');
    }
    MorphFormFixtures::panel([$resource]);
    [, $schema, $field] = morphFormSchema($record, ['subject_type' => $targetClass, 'subject_id' => 73104]);
    [, $key] = morphFormSelects($field);
    $type = $field->getTypes()[$targetClass];

    expect($target->getConnection()->getTablePrefix())->toBe('secondary_')
        ->and($type->getTitleAttribute())->toBe('title')
        ->and($type->getSearchColumns())->toEqualCanonicalizing(['title', 'id'])
        ->and($key->getSearchResults('oNdArY'))->toBe([73104 => 'Secondary label'])
        ->and($key->getSearchResults('310'))->toBe([73104 => 'Secondary label'])
        ->and($key->getOptionLabel())->toBe('Secondary label');
    $record->fill($schema->getState())->save();
    expect($record->fresh()->subject->is($target))->toBeTrue();
})->with(['inherited runtime connection' => false, 'explicit target connection' => true]);

it('uses the bound secondary source throughout the actual resource morph form', function (): void {
    MorphFormFixtures::createTargets('secondary');
    MorphFormFixtures::createRecords(connection: 'secondary');
    $defaultTarget = MorphFormNameTarget::create(['id' => 73104, 'name' => 'Default target decoy']);
    $defaultOnly = MorphFormNameTarget::create(['id' => 41999, 'name' => 'Default-only target']);
    $shared = (new MorphFormNameTarget())->setConnection('secondary')->newQuery()->create(['id' => 73104, 'name' => 'Bound-secondary shared']);
    $secondaryOnly = (new MorphFormNameTarget())->setConnection('secondary')->newQuery()->create(['id' => 85206, 'name' => 'Bound-secondary only']);
    $defaultSource = MorphFormRecord::create(['id' => 91073, 'name' => 'Default source decoy', 'subject_type' => MorphFormNameTarget::class, 'subject_id' => $defaultTarget->id]);
    $record = (new MorphFormRecord())->setConnection('secondary')->newQuery()->create(['id' => 91073, 'name' => 'Secondary source', 'subject_type' => MorphFormNameTarget::class, 'subject_id' => $shared->id]);
    $host = new MorphFormHost();
    $schema = MorphFormRecordResource::form(FilamentSchema::make($host)->model($record)->operation('edit')->statePath('morphFormData'));
    $edit = AutoEditAction::make()->livewire($host)->record($record);
    $edit->mount(['schema' => $schema]);
    /** @var MorphToSelect $field */
    $field = collect($schema->getFlatComponents(withHidden: true))->first(fn ($component) => $component instanceof MorphToSelect);
    expect($field)->toBeInstanceOf(MorphToSelect::class);
    [, $key] = morphFormSelects($field);

    expect($key->getOptionLabel())->toBe('Bound-secondary shared')
        ->and($key->getSearchResults('bOuNd-SeCoNdArY'))->toBe([$secondaryOnly->id => 'Bound-secondary only', $shared->id => 'Bound-secondary shared'])
        ->and($key->getSearchResults('5206'))->toBe([$secondaryOnly->id => 'Bound-secondary only'])
        ->and($key->getSearchResults('DeFaUlT'))->toBe([]);
    $key->state($defaultOnly->id);
    expect($key->getOptionLabel(withDefault: false))->toBeNull();
    assertInvalidMorphFormState($schema, 'subject_id');
    expect((int) $record->fresh()->subject_id)->toBe($shared->id);

    $key->state($secondaryOnly->id);
    $host->morphFormData['name'] = 'Saved on secondary';
    expect($key->getOptionLabel())->toBe('Bound-secondary only');
    $state = $schema->getState(afterValidate: fn (array $state) => $edit->data($state));
    expect((int) $state['subject_id'])->toBe($secondaryOnly->id);
    $edit->call(['schema' => $schema]);
    $saved = $record->fresh();
    expect($edit->getStatus())->toBe(ActionStatus::Success)
        ->and($saved->getConnectionName())->toBe('secondary')
        ->and($saved->name)->toBe('Saved on secondary')
        ->and((int) $saved->subject_id)->toBe($secondaryOnly->id)
        ->and($saved->subject->is($secondaryOnly))->toBeTrue()
        ->and($saved->subject->getConnectionName())->toBe('secondary')
        ->and($defaultSource->fresh()->name)->toBe('Default source decoy')
        ->and((int) $defaultSource->fresh()->subject_id)->toBe($defaultTarget->id);
});

it('keeps native morph search and selected ids isolated from joined global scopes', function (string $connection): void {
    if ($connection === 'secondary') {
        MorphFormFixtures::createTargets($connection);
        MorphFormFixtures::createRecords(connection: $connection);
    }
    MorphFormFixtures::createJoinedTargets($connection);
    MorphFormFixtures::panel([MorphFormJoinedResource::class]);
    Relation::morphMap(['joined' => MorphFormJoinedTarget::class]);
    $targets = (new MorphFormJoinedTarget())->setConnection($connection)->newQuery();
    $first = $targets->create(['id' => 73104, 'name' => 'Alpha target', 'title' => 'first-target-title', 'email' => 'first-target-email@example.test']);
    $second = $targets->create(['id' => 85206, 'name' => 'Zulu target', 'title' => 'second-target-title']);
    $unlinked = $targets->create(['id' => 96308, 'name' => 'Unlinked target']);
    DB::connection($connection)->table('morph_form_target_details')->insert([
        ['id' => 90001, 'target_id' => $first->id, 'name' => 'Zulu joined-decoy', 'title' => 'joined-decoy-title'],
        ['id' => 90002, 'target_id' => $second->id, 'name' => 'Alpha joined-decoy', 'title' => 'joined-decoy-title'],
    ]);
    $record = (new MorphFormRecord())->setConnection($connection);
    [, $schema, $field] = morphFormSchema($record, ['subject_type' => 'joined', 'subject_id' => $first->id]);
    [, $key] = morphFormSelects($field);

    expect($field->getTypes()['joined']::class)->toBe(Type::class)
        ->and($record->getConnection()->getTablePrefix())->toBe($connection === 'secondary' ? 'secondary_' : '')
        ->and($key->getSearchResults('TaRgEt'))->toBe([$first->id => 'Alpha target', $second->id => 'Zulu target'])
        ->and($key->getSearchResults('310'))->toBe([$first->id => 'Alpha target'])
        ->and($key->getSearchResults('FiRsT-TaRgEt-TiTlE'))->toBe([$first->id => 'Alpha target'])
        ->and($key->getSearchResults('FiRsT-TaRgEt-EmAiL'))->toBe([$first->id => 'Alpha target'])
        ->and($key->getSearchResults('JoInEd-DeCoY'))->toBe([])
        ->and($key->getOptionLabel())->toBe('Alpha target');
    $key->state($second->id);
    expect($key->getOptionLabel())->toBe('Zulu target');
    foreach ([90001, $unlinked->id] as $invalidId) {
        $key->state($invalidId);
        expect($key->getOptionLabel(withDefault: false))->toBeNull();
        assertInvalidMorphFormState($schema, 'subject_id');
    }

    $key->state(array_key_first($key->getSearchResults('FiRsT-TaRgEt-TiTlE')));
    $state = $schema->getState();
    expect((int) $state['subject_id'])->toBe($first->id)
        ->and($state['subject_type'])->toBe('joined');
    $record->fill($state)->save();
    expect((int) $record->fresh()->subject_id)->toBe($first->id)
        ->and($record->fresh()->getConnectionName())->toBe($connection);
    [, $editSchema, $editField] = morphFormSchema($record->fresh());
    [, $editKey] = morphFormSelects($editField);
    expect($editKey->getOptionLabel())->toBe('Alpha target')
        ->and((int) $editSchema->getState()['subject_id'])->toBe($first->id);
})->with(['unprefixed tables' => 'testing', 'prefixed tables' => 'secondary']);

it('conservatively preserves raw columns for a non-primary owner key on untyped and hydrated sources', function (bool $hydrated): void {
    MorphFormFixtures::createRecords(stringKey: true);
    $target = MorphFormNameTarget::create(['name' => 'External owner', 'external_key' => 'external-72']);
    $record = new MorphFormCustomOwnerRecord();
    if ($hydrated) {
        $record->forceFill(['subject_type' => MorphFormNameTarget::class, 'subject_id' => $target->external_key])->save();
        $record = $record->fresh();
    }
    $fields = morphFormFields($record);
    expect($fields)->toHaveKeys(['subject_type', 'subject_id'])->not->toHaveKey('subject')
        ->and($fields['subject_type'])->toBeInstanceOf(TextInput::class)
        ->and($fields['subject_id'])->toBeInstanceOf(TextInput::class);
})->with(['untyped source' => false, 'hydrated source' => true]);

it('requires the wrapper when either column is non-nullable and always requires a record for a selected type', function (bool $typeNullable, bool $idNullable): void {
    MorphFormFixtures::createRecords(typeNullable: $typeNullable, idNullable: $idNullable);
    [$host, $schema, $field] = morphFormSchema();
    [$type, $key] = morphFormSelects($field);
    $required = ! $typeNullable || ! $idNullable;

    expect($field->isRequired())->toBe($required)
        ->and($type->isRequired())->toBe($required)
        ->and($key->isRequired())->toBeFalse();
    if ($required) {
        assertInvalidMorphFormState($schema, 'subject_type');
    } else {
        expect($schema->getState())->toBe(['subject_type' => null, 'subject_id' => null]);
    }
    $host->morphFormData['subject_type'] = MorphFormNameTarget::class;
    expect($key->isRequired())->toBeTrue();
    assertInvalidMorphFormState($schema, 'subject_id');
})->with([
    'both nullable' => [true, true],
    'required type' => [false, true],
    'required id' => [true, false],
    'both required' => [false, false],
]);

it('dehydrates hidden nullable key state and persists clearing an existing morph pair', function (): void {
    $target = MorphFormNameTarget::create(['name' => 'Clear me']);
    $record = MorphFormRecord::create(['subject_type' => MorphFormNameTarget::class, 'subject_id' => $target->id]);
    [$host, $schema, $field] = morphFormSchema($record);
    [$type, $key] = morphFormSelects($field);
    $host->morphFormData['subject_type'] = null;
    $host->morphFormData['subject_id'] = null;

    expect($key->isHidden())->toBeTrue()
        ->and($schema->getState())->toBe(['subject_type' => null, 'subject_id' => null]);
    $record->fill($schema->getState())->save();
    expect($record->fresh()->subject_type)->toBeNull()
        ->and($record->fresh()->subject_id)->toBeNull()
        ->and($record->fresh()->subject)->toBeNull();
});

it('clears the id through the native type update hook even when the new type has the same id', function (): void {
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormTitleResource::class]);
    $first = MorphFormNameTarget::create(['id' => 72, 'name' => 'First']);
    $second = MorphFormTitleTarget::create(['id' => 72, 'title' => 'Second']);
    $record = MorphFormRecord::create(['subject_type' => MorphFormNameTarget::class, 'subject_id' => $first->id]);
    [$host, $schema, $field] = morphFormSchema($record);
    [$type, $key] = morphFormSelects($field);
    $type->state(MorphFormTitleTarget::class)->callAfterStateUpdated();

    expect($host->morphFormData['subject_id'])->toBeNull();
    assertInvalidMorphFormState($schema, 'subject_id');
    $key->state($second->id);
    expect($key->getOptionLabel())->toBe('Second');
    $record->fill($schema->getState())->save();
    expect($record->fresh()->subject->is($second))->toBeTrue();

    $type->state(null)->callAfterStateUpdated();
    expect($host->morphFormData['subject_id'])->toBeNull()
        ->and($schema->getState())->toBe(['subject_type' => null, 'subject_id' => null]);
});

it('rejects tampered types nonexistent ids and globally scoped-out ids with native select validation', function (string $tampering): void {
    MorphFormFixtures::panel([MorphFormScopedResource::class, MorphFormDeniedResource::class]);
    $visible = MorphFormScopedTarget::create(['name' => 'Visible', 'active' => true]);
    $hidden = MorphFormScopedTarget::create(['name' => 'Hidden', 'active' => false]);
    $record = MorphFormRecord::create(['subject_type' => MorphFormScopedTarget::class, 'subject_id' => $visible->id]);
    [$host, $schema] = morphFormSchema($record);
    if ($tampering === 'type') {
        $host->morphFormData['subject_type'] = MorphFormDeniedTarget::class;
    } else {
        $host->morphFormData['subject_id'] = $tampering === 'missing' ? 999999 : $hidden->id;
    }

    assertInvalidMorphFormState($schema, $tampering === 'type' ? 'subject_type' : 'subject_id');
    expect($record->fresh()->subject_type)->toBe(MorphFormScopedTarget::class)
        ->and((int) $record->fresh()->subject_id)->toBe($visible->id);
})->with(['arbitrary type' => 'type', 'nonexistent record' => 'missing', 'scoped-out record' => 'scoped']);

it('queries concrete target models with global scopes rather than resource getEloquentQuery', function (): void {
    MorphFormFixtures::panel([MorphFormScopedResource::class]);
    $visible = MorphFormScopedTarget::create(['name' => 'Search visible', 'email' => 'scope-needle@example.test']);
    $hidden = MorphFormScopedTarget::create(['name' => 'Search hidden', 'email' => 'scope-needle@example.test', 'active' => false]);
    [$host, $schema, $field] = morphFormSchema(state: ['subject_type' => MorphFormScopedTarget::class, 'subject_id' => $visible->id]);
    [, $key] = morphFormSelects($field);

    expect($key->getSearchResults('ScOpE-NeEdLe'))->toBe([$visible->id => 'Search visible'])
        ->and($key->getOptionLabel())->toBe('Search visible');
    $key->state($hidden->id);
    expect($key->getOptionLabel(withDefault: false))->toBeNull();
    assertInvalidMorphFormState($schema, 'subject_id');
});

it('guesses labels per target from native name title or primary key and uses the key for blank titles', function (): void {
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormTitleResource::class, MorphFormIntegerKeyResource::class]);
    $named = MorphFormNameTarget::create(['name' => 'Preferred name', 'title' => 'Not the label']);
    $titled = MorphFormTitleTarget::create(['title' => 'Title only']);
    $keyOnly = MorphFormIntegerKeyTarget::create(['target_number' => 73104]);
    $nullName = MorphFormNameTarget::create(['name' => null, 'title' => 'Do not switch title columns']);
    $emptyName = MorphFormNameTarget::create(['name' => '', 'title' => 'Do not switch title columns']);

    foreach ([[$named, 'name', 'Preferred name'], [$titled, 'title', 'Title only'], [$keyOnly, 'target_number', '73104'], [$nullName, 'name', (string) $nullName->id], [$emptyName, 'name', (string) $emptyName->id]] as [$target, $attribute, $label]) {
        [, $schema, $field] = morphFormSchema(state: ['subject_type' => $target::class, 'subject_id' => $target->getKey()]);
        [, $key] = morphFormSelects($field);
        expect($field->getTypes()[$target::class]->getTitleAttribute())->toBe($attribute)
            ->and($key->getOptionLabel())->toBe($label)
            ->and($key->getSearchResults((string) $target->getKey()))->toHaveKey($target->getKey())
            ->and($schema->getState()['subject_id'])->toEqual($target->getKey());
        expect($key->getSearchResults((string) $target->getKey())[$target->getKey()])->toBe($label);
    }
});

it('searches real records by id name title email and the deduplicated actual primary key without searching notes', function (): void {
    MorphFormFixtures::createRecords(stringKey: true);
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormKeyResource::class]);
    $named = MorphFormNameTarget::create(['id' => 73104, 'name' => 'Named label', 'title' => 'alternate-needle', 'email' => 'mail-needle@example.test', 'notes' => 'notes-only-needle']);
    $custom = MorphFormKeyTarget::create(['target_key' => 'custom-West-72', 'id' => 85206, 'name' => 'Custom label', 'title' => 'alternate-needle', 'email' => 'mail-needle@example.test']);

    foreach ([[$named, ['name', 'id', 'title', 'email'], ['310', 'nAmEd', 'AlTeRnAtE', 'MaIl-NeEdLe']], [$custom, ['name', 'target_key', 'id', 'title', 'email'], ['520', 'cUsToM', 'AlTeRnAtE', 'MaIl-NeEdLe', 'm-WeSt-']]] as [$target, $columns, $terms]) {
        [, , $field] = morphFormSchema(state: ['subject_type' => $target::class]);
        [, $key] = morphFormSelects($field);
        $actualColumns = $field->getTypes()[$target::class]->getSearchColumns();
        expect($actualColumns)->toEqualCanonicalizing($columns)
            ->and($actualColumns)->toHaveCount(count(array_unique($actualColumns)));
        foreach ($terms as $term) {
            expect($key->getSearchResults($term))->toBe([$target->getKey() => $target->name]);
        }
        expect($key->getSearchResults('notes-only-needle'))->toBe([]);
    }
});

it('retains the label search but filters hidden visible encrypted and hashed additional candidates', function (): void {
    MorphFormFixtures::panel([MorphFormSafeResource::class]);
    DB::table('morph_form_names')->insert(['id' => 73104, 'name' => 'Safe label', 'title' => 'private-needle', 'email' => 'private-needle@example.test']);
    foreach ([
        [['name', 'id', 'title', 'email'], [], [], ['name']],
        [[], ['name'], [], ['name']],
        [[], [], ['title' => 'encrypted', 'email' => 'encrypted:array'], ['name', 'id']],
        [[], [], ['title' => 'hashed', 'email' => 'hashed'], ['name', 'id']],
    ] as [$hidden, $visible, $casts, $columns]) {
        config(['morph-form.hidden' => $hidden, 'morph-form.visible' => $visible, 'morph-form.casts' => $casts]);
        [, , $field] = morphFormSchema(state: ['subject_type' => MorphFormSafeTarget::class]);
        [, $key] = morphFormSelects($field);
        expect($field->getTypes()[MorphFormSafeTarget::class]->getSearchColumns())->toEqualCanonicalizing($columns)
            ->and($key->getSearchResults('SaFe'))->toBe([73104 => 'Safe label'])
            ->and($key->getSearchResults('private-needle'))->toBe([])
            ->and($key->getSearchResults('310'))->toBe(in_array('id', $columns, true) ? [73104 => 'Safe label'] : []);
    }
});

it('does not issue SQL against missing email columns or accessor-only candidates', function (): void {
    Schema::table('morph_form_names', fn (Blueprint $table) => $table->dropColumn('email'));
    MorphFormFixtures::panel([MorphFormAccessorResource::class]);
    $target = MorphFormAccessorTarget::create(['id' => 73104, 'name' => 'Accessor safe label']);
    [, , $field] = morphFormSchema(state: ['subject_type' => MorphFormAccessorTarget::class]);
    [, $key] = morphFormSelects($field);

    expect($field->getTypes()[MorphFormAccessorTarget::class]->getSearchColumns())->toEqualCanonicalizing(['name', 'id', 'title'])
        ->and($key->getSearchResults('310'))->toBe([$target->id => 'Accessor safe label'])
        ->and($key->getSearchResults('accessor-only-needle'))->toBe([]);
});

it('groups and persists multiple snake-case pairs under their relationship names regardless of column order', function (bool $reverse): void {
    MorphFormFixtures::createRecords(reverse: $reverse, multiple: true);
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormTitleResource::class]);
    Relation::morphMap(['named' => MorphFormNameTarget::class, 'titled' => MorphFormTitleTarget::class]);
    $named = MorphFormNameTarget::create(['name' => 'Subject']);
    $titled = MorphFormTitleTarget::create(['title' => 'Review target']);
    $fields = morphFormFields();
    expect(array_keys($fields))->toEqualCanonicalizing(['id', 'name', 'subject', 'reviewTarget']);
    $host = new MorphFormHost();
    $schema = FilamentSchema::make($host)->model(MorphFormRecord::class)->statePath('morphFormData')->components($fields);
    $schema->fill(['subject_type' => 'named', 'subject_id' => $named->id, 'review_target_type' => 'titled', 'review_target_id' => $titled->id]);
    [$reviewType, $reviewKey] = morphFormSelects($fields['reviewTarget']);
    $state = $schema->getState();

    expect($fields['reviewTarget']->getName())->toBe('reviewTarget')
        ->and($reviewType->getName())->toBe('review_target_type')
        ->and($reviewKey->getName())->toBe('review_target_id')
        ->and($reviewKey->getOptionLabel())->toBe('Review target')
        ->and($state)->toHaveKeys(['subject_type', 'subject_id', 'review_target_type', 'review_target_id'])
        ->not->toHaveKey('subject')->not->toHaveKey('reviewTarget');
    $record = MorphFormRecord::create($state)->fresh();
    expect($record->subject->is($named))->toBeTrue()->and($record->reviewTarget->is($titled))->toBeTrue();
})->with(['type before id' => false, 'id before type' => true]);

it('does not group when either raw column is excluded even if that column also has an overwrite or dictionary', function (string $column): void {
    $fields = morphFormFields(except: [$column], overwrites: [$column => TextInput::make($column)], dictionary: [$column => [1 => 'Excluded']]);
    $other = $column === 'subject_type' ? 'subject_id' : 'subject_type';
    expect($fields)->not->toHaveKey('subject')->not->toHaveKey($column)
        ->and($fields[$other])->toBeInstanceOf(TextInput::class);
})->with(['subject_type', 'subject_id']);

it('honors raw overwrites and dictionaries on either pair column instead of automatically grouping', function (string $column, bool $dictionary): void {
    $override = TextInput::make($column)->label('Raw override');
    $options = $column === 'subject_id' ? [72 => 'Fixed record'] : ['fixed' => 'Fixed type'];
    $fields = morphFormFields(overwrites: $dictionary ? [] : [$column => $override], dictionary: [$column => $options]);

    expect($fields)->toHaveKeys(['subject_type', 'subject_id'])->not->toHaveKey('subject');
    if ($dictionary) {
        expect($fields[$column]::class)->toBe(Select::class)
            ->and($fields[$column]->getOptions())->toBe($options);
    } else {
        expect($fields[$column])->toBe($override);
    }
})->with([
    'type overwrite' => ['subject_type', false],
    'id overwrite' => ['subject_id', false],
    'type dictionary' => ['subject_type', true],
    'id dictionary' => ['subject_id', true],
]);

it('honors a wrapper overwrite keyed by the relationship rather than either physical column', function (): void {
    $override = MorphToSelect::make('subject')->label('Manual subject')->types([Type::make(MorphFormNameTarget::class)->titleAttribute('name')]);
    $fields = morphFormFields(overwrites: ['subject' => $override]);
    expect(array_keys($fields))->toEqualCanonicalizing(['id', 'name', 'subject'])
        ->and($fields['subject'])->toBe($override)
        ->and($fields['subject']->getLabel())->toBe('Manual subject');
});

it('preserves both raw inputs when no accessible compatible target resources remain', function (): void {
    Relation::morphMap(['map-only' => MorphFormMapOnlyTarget::class]);
    foreach ([[], [MorphFormDeniedResource::class], [MorphFormKeyResource::class]] as $resources) {
        MorphFormFixtures::panel($resources);
        $fields = morphFormFields();
        expect($fields)->toHaveKeys(['subject_type', 'subject_id'])->not->toHaveKey('subject')
            ->and($fields['subject_type'])->toBeInstanceOf(TextInput::class)
            ->and($fields['subject_id'])->toBeInstanceOf(TextInput::class)
            ->and($fields['subject_id']->isNumeric())->toBeTrue();
    }
});

it('does not group unverified mismatched or incomplete conventional pairs', function (): void {
    MorphFormFixtures::createRecords(multiple: true);
    foreach ([new MorphFormRawRecord(), new MorphFormUnverifiedRecord()] as $record) {
        $fields = morphFormFields($record);
        expect($fields)->toHaveKeys(['subject_type', 'subject_id', 'review_target_type', 'review_target_id'])
            ->not->toHaveKey('subject')->not->toHaveKey('reviewTarget');
    }
    foreach (['subject_type', 'subject_id'] as $missing) {
        MorphFormFixtures::createRecords();
        Schema::table('morph_form_records', fn (Blueprint $table) => $table->dropColumn($missing));
        $other = $missing === 'subject_type' ? 'subject_id' : 'subject_type';
        $fields = morphFormFields();
        expect($fields)->toHaveKey($other)->not->toHaveKey('subject')->not->toHaveKey($missing)
            ->and($fields[$other])->toBeInstanceOf(TextInput::class);
    }
});

it('leaves existing table and infolist morph display grouping unchanged', function (): void {
    $target = MorphFormNameTarget::create(['name' => 'Display target']);
    $record = MorphFormRecord::create(['subject_type' => MorphFormNameTarget::class, 'subject_id' => $target->id]);
    $columns = TableGenerator::makeForModel($record);
    $host = new MorphFormHost();
    $host->setMorphFormTable(Table::make($host)->columns($columns));
    $infolist = FilamentSchema::make(new MorphFormHost())->model($record)->components(InfolistGenerator::makeForModel($record));
    $entries = collect($infolist->getFlatComponents(withHidden: true))->filter(fn ($component) => $component instanceof TextEntry)->keyBy(fn ($entry): string => (string) $entry->getName());

    expect(array_keys($columns))->toEqualCanonicalizing(['id', 'name', 'subject'])
        ->and($columns['subject']::class)->toBe(TextColumn::class)
        ->and($columns['subject']->getName())->toBe('subject.name')
        ->and($columns['subject']->record($record)->getState())->toBe('Display target')
        ->and($entries)->toHaveKey('subject.name')->not->toHaveKey('subject_type')->not->toHaveKey('subject_id')
        ->and($entries['subject.name']->getState())->toBe('Display target');
});

it('generates a native morph form and creates the selected subject through a mounted relation manager', function (bool $resourceBacked): void {
    Schema::table('morph_form_records', fn (Blueprint $table) => $table->foreignId('owner_id')->nullable());
    Relation::morphMap(['named' => MorphFormNameTarget::class]);
    $owner = MorphFormOwner::create(['name' => 'Relation owner']);
    $target = MorphFormNameTarget::create(['id' => 73104, 'name' => 'Related subject', 'email' => 'manager-search@example.test']);
    $class = $resourceBacked
        ? RelationManagerMounter::makeFromResource(MorphFormRecordResource::class, 'morphFormRecords', associateAttachActions: false)
        : RelationManagerMounter::makeStandalone('morphFormRecords', ['name'], associateAttachActions: false);
    $manager = new $class();
    $manager->ownerRecord = $owner;
    $manager->pageClass = MorphFormHost::class;
    $form = $manager->form(FilamentSchema::make($manager)
        ->model($manager->getRelationship()->getRelated())
        ->operation('create')
        ->statePath('mountedActions.0.data'));
    $form->fill(['name' => 'Related source']);
    $fields = collect($form->getComponents(withHidden: true))->keyBy(fn ($field): string => (string) $field->getName());

    expect($manager::getRelatedResource())->toBe($resourceBacked ? MorphFormRecordResource::class : null)
        ->and($fields->keys()->all())->toEqualCanonicalizing(['id', 'name', 'subject'])
        ->and($fields)->not->toHaveKey('owner_id')
        ->and($fields['subject']::class)->toBe(MorphToSelect::class)
        ->and($fields['subject']->getName())->toBe('subject');
    /** @var MorphToSelect $field */
    $field = $fields['subject'];
    [$type, $key] = morphFormSelects($field);
    expect($type->getName())->toBe('subject_type')
        ->and($type->getStatePath())->toBe('mountedActions.0.data.subject_type')
        ->and($key->getName())->toBe('subject_id')
        ->and($key->getStatePath())->toBe('mountedActions.0.data.subject_id');
    $type->state('named')->callAfterStateUpdated();
    expect($key->getSearchResults('MaNaGeR-SeArCh'))->toBe([$target->id => 'Related subject']);
    $key->state($target->id);
    $state = $form->getState();
    expect($state)->toHaveKeys(['subject_type', 'subject_id'])->not->toHaveKey('owner_id')->not->toHaveKey('subject')
        ->and($state['subject_type'])->toBe('named')
        ->and((int) $state['subject_id'])->toBe($target->id);

    $create = AutoCreateAction::make()->livewire($manager)->model(MorphFormRecord::class)
        ->relationship(fn () => $manager->getRelationship())
        ->data($state);
    $create->call(['schema' => $form]);
    $record = $create->getRecord()->fresh();
    expect($create->getStatus())->toBe(ActionStatus::Success)
        ->and((int) $record->owner_id)->toBe($owner->id)
        ->and($record->subject_type)->toBe('named')
        ->and((int) $record->subject_id)->toBe($target->id)
        ->and($record->subject->is($target))->toBeTrue()
        ->and($owner->morphFormRecords()->sole()->is($record))->toBeTrue()
        ->and($form->getRecord()->is($record))->toBeTrue();
})->with(['standalone' => false, 'resource-backed' => true]);

it('runs generated create and edit forms through native action mount validation dehydration and persistence', function (): void {
    MorphFormFixtures::panel([MorphFormNameResource::class, MorphFormTitleResource::class]);
    $named = MorphFormNameTarget::create(['name' => 'Created target']);
    $titled = MorphFormTitleTarget::create(['title' => 'Edited target']);
    $host = new MorphFormHost();
    $schema = MorphFormRecordResource::form(FilamentSchema::make($host)->model(MorphFormRecord::class)->operation('create')->statePath('morphFormData'));
    $schema->fill(['name' => 'Created source', 'subject_type' => MorphFormNameTarget::class, 'subject_id' => $named->id]);
    $wrappers = collect($schema->getFlatComponents(withHidden: true))->filter(fn ($component) => $component instanceof MorphToSelect);
    expect($wrappers)->toHaveCount(1);
    $create = AutoCreateAction::make()->livewire($host)->model(MorphFormRecord::class)->data($schema->getState());
    $create->call(['schema' => $schema]);
    $record = $create->getRecord()->fresh();
    expect($create->getStatus())->toBe(ActionStatus::Success)
        ->and($record->subject->is($named))->toBeTrue()
        ->and($schema->getRecord()->is($record))->toBeTrue();

    $editHost = new MorphFormHost();
    $editSchema = MorphFormRecordResource::form(FilamentSchema::make($editHost)->model($record)->operation('edit')->statePath('morphFormData'));
    $edit = AutoEditAction::make()->livewire($editHost)->record($record);
    $edit->mount(['schema' => $editSchema]);
    /** @var MorphToSelect $field */
    $field = collect($editSchema->getFlatComponents(withHidden: true))->first(fn ($component) => $component instanceof MorphToSelect);
    expect($field)->toBeInstanceOf(MorphToSelect::class);
    [$type, $key] = morphFormSelects($field);
    expect($editHost->morphFormData['subject_type'])->toBe(MorphFormNameTarget::class)
        ->and($key->getOptionLabel())->toBe('Created target');
    $type->state(MorphFormTitleTarget::class)->callAfterStateUpdated();
    expect($editHost->morphFormData['subject_id'])->toBeNull();
    $key->state($titled->id);
    $editSchema->getState(afterValidate: fn (array $state) => $edit->data($state));
    $edit->call(['schema' => $editSchema]);

    expect($edit->getStatus())->toBe(ActionStatus::Success)
        ->and($record->fresh()->subject_type)->toBe(MorphFormTitleTarget::class)
        ->and($record->fresh()->subject->is($titled))->toBeTrue();
});
