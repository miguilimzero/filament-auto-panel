<?php

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelAccessorUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelEncryptedArrayEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelEncryptedArrayObjectEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelEncryptedCollectionEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelEncryptedEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelHashedEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelHiddenEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelHtmlableUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelJoinedUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelKeyContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelKeyUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelLegacyEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelNamedUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelNonvisibleEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelParameterizedEncryptedArrayObjectEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelParameterizedEncryptedCollectionEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelPartialUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelPlainUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelTitleContact;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipLabelVisibleEmailUser;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\RelationshipOptionLabelFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;

require_once __DIR__ . '/../Fixtures/RelationshipOptionLabelFixtures.php';
require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';

beforeEach(function (): void {
    RelationshipOptionLabelFixtures::create();
    RelationshipLabelUser::$avatarCalls = 0;
    RelationshipLabelAccessorUser::$emailValue = 'accessor@example.test';
});

afterEach(function (): void {
    RelationshipOptionLabelFixtures::drop();
});

function relationshipLabelSelect(string $related = RelationshipLabelContact::class, ?RelationshipLabelRecord $model = null): Select
{
    $model ??= new RelationshipLabelRecord();
    $model->ownerModel = $related;
    $field = FormGenerator::makeForModel($model, relationManagerView: true)['owner_id'];
    FilamentSchema::make(new UserFieldListRecords())
        ->model($model)
        ->statePath('userFieldData')
        ->components([$field])
        ->getFlatComponents(withHidden: true);

    return $field;
}

function relationshipLabelVariants(Select $field, string $key, ?string $search = null): array
{
    $field->state($key);
    $selected = $field->getOptionLabel();
    $results = $field->getSearchResults($search ?? $key);
    $options = $field->preload()->getOptions();
    expect($results)->toHaveKey($key)
        ->and($options)->toHaveKey($key)
        ->and($results[$key])->toBe($selected)
        ->and($options[$key])->toBe($selected)
        ->and($field->getState())->toBe($key);

    return [$selected, $results[$key], $options[$key]];
}

function relationshipLabelDom(string $html): DOMXPath
{
    $document = new DOMDocument();
    expect($document->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
    ))->toBeTrue();

    return new DOMXPath($document);
}

function expectedRelationshipEmailLabel(string $label, string $email): string
{
    return '<span style="display:block">' . $label . '</span>'
        . ' <span class="fi-sc-text" style="display:block">' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
}

function assertRelationshipLabelHtml(string $html, string $key, string $text, ?string $avatar = null, ?string $email = null): void
{
    $dom = relationshipLabelDom($html);
    expect(trim($dom->query('//body')->item(0)->textContent))->toBe($text . ($email === null ? '' : ' ' . $email))
        ->and($dom->query('//b')->length)->toBe(1)
        ->and($dom->query('//b')->item(0)->textContent)->toBe('#' . $key)
        ->and($dom->query('//b/*')->length)->toBe(0)
        ->and($dom->query('//body//*[not(self::b or self::img or self::span)]')->length)->toBe(0)
        ->and($dom->query('//*[@onerror or @onload or @onclick or @onfocus or @autofocus]')->length)->toBe(0)
        ->and($dom->query('//img')->length)->toBe($avatar === null ? 0 : 1)
        ->and($dom->query('//span')->length)->toBe(($avatar === null ? 0 : 2) + ($email === null ? 0 : 2))
        ->and($dom->query('//b/@*')->length)->toBe(0)
        ->and($dom->query('//span[@class]')->length)->toBe($email === null ? 0 : 1)
        ->and($html)->not->toContain('ui-avatars.com', '/has-avatar-fallback/')
        ->and(RelationshipLabelUser::$avatarCalls)->toBe(0);

    foreach ($dom->query('//span') as $span) {
        foreach ($span->attributes as $attribute) {
            expect($attribute->name)->toBeIn(['class', 'style']);
        }
    }

    $contentPath = '//body';

    if ($avatar !== null) {
        expect($dom->query('//body/*')->length)->toBe(1)
            ->and($dom->query('//body/span')->item(0)->getAttribute('style'))->toBe('display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center')
            ->and($dom->query('//body/span/*')->length)->toBe(2)
            ->and($dom->query('//body/span/*[1][self::img]')->length)->toBe(1)
            ->and($dom->query('//body/span/*[2][self::span]')->length)->toBe(1)
            ->and($dom->query('//body/span/span')->item(0)->getAttribute('style'))->toBe('min-width:0');
        $contentPath = '//body/span/span';
    }

    if ($email === null) {
        expect($dom->query($contentPath . '/span')->length)->toBe(0)
            ->and($dom->query('//*[@class and not(self::img)]')->length)->toBe(0);
    } else {
        $firstLine = $dom->query($contentPath . '/*[1][self::span]')->item(0);
        $secondLine = $dom->query($contentPath . '/*[2][self::span]')->item(0);
        expect($dom->query($contentPath . '/*')->length)->toBe(2)
            ->and($firstLine)->toBeInstanceOf(DOMElement::class)
            ->and($secondLine)->toBeInstanceOf(DOMElement::class)
            ->and($firstLine->textContent)->toBe($text)
            ->and($firstLine->getAttribute('style'))->toBe('display:block')
            ->and($firstLine->getAttribute('class'))->toBe('')
            ->and($dom->query($contentPath . '/span[1]/b')->length)->toBe(1)
            ->and($secondLine->textContent)->toBe($email)
            ->and($secondLine->getAttribute('style'))->toBe('display:block')
            ->and($secondLine->getAttribute('class'))->toBe('fi-sc-text')
            ->and($secondLine->childElementCount)->toBe(0);
    }

    if ($avatar === null) {
        return;
    }

    $image = $dom->query('//img')->item(0);
    expect($image->getAttribute('src'))->toBe($avatar)
        ->and($image->getAttribute('alt'))->toBe('')
        ->and($image->getAttribute('loading'))->toBe('lazy')
        ->and(preg_split('/\s+/', trim($image->getAttribute('class'))))->toContain('fi-avatar', 'fi-circular', 'fi-size-sm')
        ->and($image->getAttribute('style'))->toContain('width:1.5rem', 'height:1.5rem');
    foreach ($image->attributes as $attribute) {
        expect($attribute->name)->toBeIn(['src', 'alt', 'loading', 'class', 'style']);
    }
}

it('uses native Select components with HTML record callbacks and non-native dropdown rendering for every generated belongs-to choice', function (string $related): void {
    $field = relationshipLabelSelect($related);

    expect($field::class)->toBe(Select::class)
        ->and($field->getName())->toBe('owner_id')
        ->and($field->getRelationshipName())->toBe('owner')
        ->and($field->isHtmlAllowed())->toBeTrue()
        ->and($field->isNative())->toBeFalse()
        ->and($field->hasOptionLabelFromRecordUsingCallback())->toBeTrue()
        ->and($field->isSearchable())->toBeTrue()
        ->and($field->isSearchForcedCaseInsensitive())->toBeTrue();
})->with(['generic' => [RelationshipLabelContact::class], 'authenticatable' => [RelationshipLabelUser::class]]);

it('never appends email or an avatar to a non-authenticatable choice even when both attributes exist', function (): void {
    $contact = RelationshipLabelContact::create([
        'id' => 'contact-73104', 'name' => 'Generic <b>contact</b> & Co',
        'email' => 'private@example.test', 'profile_photo_url' => '/avatars/not-a-user.svg',
    ]);
    $field = relationshipLabelSelect();

    expect($contact)->not->toBeInstanceOf(Authenticatable::class);
    foreach (relationshipLabelVariants($field, $contact->id, 'PRIVATE@EXAMPLE') as $label) {
        expect($label)->toBe('<b>#contact-73104</b> - Generic &lt;b&gt;contact&lt;/b&gt; &amp; Co');
        assertRelationshipLabelHtml($label, $contact->id, '#contact-73104 - Generic <b>contact</b> & Co');
    }
});

it('uses a native muted text class with compiled light and dark colors without a custom theme', function (): void {
    $native = relationshipLabelDom(\Filament\Schemas\Components\Text::make('native@example.test')->color('gray')->toEmbeddedHtml());
    expect(trim($native->query('//body/span')->item(0)->getAttribute('class')))->toBe('fi-sc-text');

    $themePath = dirname((new ReflectionClass(\Filament\Panel::class))->getFileName(), 2) . '/dist/theme.css';
    $css = file_get_contents($themePath);
    expect($css)
        ->toMatch('/\.fi-sc-text:not\(\.fi-badge\)\s*\{[^}]*\bcolor:\s*var\(--gray-600\)/')
        ->toMatch('/\.fi-sc-text:not\(\.fi-badge\):where\(\.dark,\s*\.dark \*\)\s*\{[^}]*\bcolor:\s*var\(--gray-400\)/');
});

it('adds an optional email and a sanitized default avatar for genuine authenticated model choices', function (string $related): void {
    $user = $related::create([
        'id' => 'user-73104', 'name' => 'Alice & Co',
        'email' => 'alice@example.test', 'profile_photo_url' => '/avatars/alice.svg?a=1&b=2',
    ]);
    $this->actingAs($user);
    expect(auth()->user())->toBeInstanceOf(Authenticatable::class);
    $field = relationshipLabelSelect($related);
    $labels = relationshipLabelVariants($field, $user->id, 'ALICE@EXAMPLE');

    expect(array_unique($labels))->toHaveCount(1);
    foreach ($labels as $label) {
        expect($label)->toContain(expectedRelationshipEmailLabel('<b>#user-73104</b> - Alice &amp; Co', 'alice@example.test'));
        assertRelationshipLabelHtml($label, $user->id, '#user-73104 - Alice & Co', $user->profile_photo_url, 'alice@example.test');
    }
    expect($field->getOptionsForJs())->toContainEqual([
        'label' => $labels[0], 'value' => $user->id, 'isDisabled' => false,
    ]);
})->with(['HasAvatar model' => [RelationshipLabelUser::class], 'without HasAvatar' => [RelationshipLabelPlainUser::class]]);

it('escapes malicious primary keys names and email as strings without trusting Htmlable values', function (string $related): void {
    $key = 'key-<img src=x onerror="alert(1)">&quot;';
    $name = 'Alice </b><script>alert("name")</script> &quot; & Co';
    $email = '"><img src=x onerror="alert(2)">@example.test';
    $user = $related::create(['id' => $key, 'name' => $name, 'email' => $email]);
    $expected = expectedRelationshipEmailLabel(
        '<b>#' . htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b> - '
            . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $email,
    );

    foreach (relationshipLabelVariants(relationshipLabelSelect($related), $user->getKey(), 'ALICE') as $label) {
        expect($label)->toBe($expected);
        assertRelationshipLabelHtml($label, $key, '#' . $key . ' - ' . $name, email: $email);
    }
})->with(['database strings' => RelationshipLabelUser::class, 'Htmlable accessors' => RelationshipLabelHtmlableUser::class]);

it('renders only a bold actual key for missing or blank guessed titles without a dangling separator', function (?string $name): void {
    $contact = RelationshipLabelContact::create(['id' => 'key-only-73104', 'name' => $name, 'title' => 'Not the guessed title']);
    foreach (relationshipLabelVariants(relationshipLabelSelect(), $contact->id) as $label) {
        expect($label)->toBe('<b>#key-only-73104</b>');
    }
})->with(['null' => [null], 'empty' => [''], 'whitespace' => [" \t\n"]]);

it('preserves zero titles and zero email rather than treating safe scalar strings as missing', function (): void {
    RelationshipLabelUser::create(['id' => 'zero-user', 'name' => '0', 'email' => '0']);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'zero-user') as $label) {
        expect($label)->toBe(expectedRelationshipEmailLabel('<b>#zero-user</b> - 0', '0'));
        assertRelationshipLabelHtml($label, 'zero-user', '#zero-user - 0', email: '0');
    }
});

it('does not duplicate the key when the guessed title is the primary key for generic or authenticatable choices', function (string $related): void {
    $related::create(['id' => 'only-key']);
    $field = relationshipLabelSelect($related);

    expect($field->getRelationshipTitleAttribute())->toBe('id');
    foreach (relationshipLabelVariants($field, 'only-key') as $label) {
        expect($label)->toBe('<b>#only-key</b>');
    }
})->with(['generic' => [RelationshipLabelKeyContact::class], 'authenticatable' => [RelationshipLabelKeyUser::class]]);

it('uses the guessed database title without automatically applying HasName or a name accessor', function (string $related): void {
    $related::create(['id' => 'title-73104', 'title' => 'Database <b>title</b>']);
    $field = relationshipLabelSelect($related);
    expect($field->getRelationshipTitleAttribute())->toBe('title');

    foreach (relationshipLabelVariants($field, 'title-73104', 'DATABASE') as $label) {
        expect($label)->toBe('<b>#title-73104</b> - Database &lt;b&gt;title&lt;/b&gt;');
        assertRelationshipLabelHtml($label, 'title-73104', '#title-73104 - Database <b>title</b>');
    }
})->with(['generic' => [RelationshipLabelTitleContact::class], 'authenticatable' => [RelationshipLabelNamedUser::class]]);

it('omits absent or blank user email without an empty second line', function (?string $email, ?string $avatar): void {
    RelationshipLabelUser::create(['id' => 'blank-email', 'name' => 'No email', 'email' => $email, 'profile_photo_url' => $avatar]);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'blank-email') as $label) {
        if ($avatar === null) {
            expect($label)->toBe('<b>#blank-email</b> - No email');
        }
        assertRelationshipLabelHtml($label, 'blank-email', '#blank-email - No email', $avatar);
    }
})->with(['null' => [null], 'empty' => [''], 'whitespace' => [" \t\n"]])
    ->with(['without avatar' => [null], 'with avatar' => ['/avatars/no-email.svg']]);

it('keeps a key-only first line above safe email when the guessed user title is blank', function (?string $name): void {
    RelationshipLabelUser::create(['id' => 'blank-name', 'name' => $name, 'email' => 'key-only@example.test']);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'blank-name') as $label) {
        expect($label)->toBe(expectedRelationshipEmailLabel('<b>#blank-name</b>', 'key-only@example.test'));
        assertRelationshipLabelHtml($label, 'blank-name', '#blank-name', email: 'key-only@example.test');
    }
})->with(['null' => [null], 'empty' => [''], 'whitespace' => [" \t\n"]]);

it('does not read or disclose hidden nonvisible hashed or encrypted user email', function (string $related): void {
    // Invalid ciphertext makes reading a forbidden email observable, not merely a text assertion.
    DB::table('relationship_label_contacts')->insert(['id' => 'private-email', 'name' => 'Visible user', 'email' => 'not-ciphertext-private@example.test']);
    $field = relationshipLabelSelect($related);

    expect($field->getSearchColumns())->not->toContain('email');
    foreach (relationshipLabelVariants($field, 'private-email', 'VISIBLE') as $label) {
        expect($label)->toBe('<b>#private-email</b> - Visible user');
        assertRelationshipLabelHtml($label, 'private-email', '#private-email - Visible user');
    }
    expect($field->getSearchResults('not-ciphertext-private'))->toBe([]);
})->with([
    'hidden' => RelationshipLabelHiddenEmailUser::class,
    'outside visible allowlist' => RelationshipLabelNonvisibleEmailUser::class,
    'hashed' => RelationshipLabelHashedEmailUser::class,
    'encrypted' => RelationshipLabelEncryptedEmailUser::class,
    'encrypted array' => RelationshipLabelEncryptedArrayEmailUser::class,
    'encrypted collection class' => RelationshipLabelEncryptedCollectionEmailUser::class,
    'encrypted collection class with arguments' => RelationshipLabelParameterizedEncryptedCollectionEmailUser::class,
    'encrypted array object class' => RelationshipLabelEncryptedArrayObjectEmailUser::class,
    'encrypted array object class with arguments' => RelationshipLabelParameterizedEncryptedArrayObjectEmailUser::class,
]);

it('includes email when it is explicitly visible and otherwise safe', function (): void {
    RelationshipLabelVisibleEmailUser::create(['id' => 'visible-email', 'name' => 'Visible user', 'email' => 'visible@example.test']);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelVisibleEmailUser::class), 'visible-email') as $label) {
        expect($label)->toBe(expectedRelationshipEmailLabel('<b>#visible-email</b> - Visible user', 'visible@example.test'));
        assertRelationshipLabelHtml($label, 'visible-email', '#visible-email - Visible user', email: 'visible@example.test');
    }
});

it('supports safe scalar and stringable email accessors without adding accessor-only SQL searches', function (mixed $email, ?string $expectedEmail): void {
    Schema::table('relationship_label_contacts', fn ($table) => $table->dropColumn('email'));
    RelationshipLabelAccessorUser::$emailValue = $email;
    RelationshipLabelAccessorUser::create(['id' => 'accessor-user', 'name' => 'Accessor user']);
    $field = relationshipLabelSelect(RelationshipLabelAccessorUser::class);

    expect($field->getSearchColumns())->not->toContain('email');
    foreach (relationshipLabelVariants($field, 'accessor-user', 'ACCESSOR USER') as $label) {
        assertRelationshipLabelHtml($label, 'accessor-user', '#accessor-user - Accessor user', '/accessor-avatars/accessor-user.svg', $expectedEmail);
    }
})->with([
    'string' => ['accessor@example.test', 'accessor@example.test'],
    'integer' => [42, '42'],
    'float' => [4.5, '4.5'],
    'zero' => [0, '0'],
    'false' => [false, null],
    'stringable HTML' => [new HtmlString('<script>email</script>@example.test'), '<script>email</script>@example.test'],
    'array' => [['not-safe@example.test'], null],
    'non-stringable object' => [new stdClass(), null],
    'null' => [null, null],
]);

it('supports legacy email accessors even when the email column does not exist', function (): void {
    Schema::table('relationship_label_contacts', fn ($table) => $table->dropColumn('email'));
    RelationshipLabelLegacyEmailUser::create(['id' => 'legacy-user', 'name' => 'Legacy user']);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelLegacyEmailUser::class), 'legacy-user') as $label) {
        expect($label)->toBe(expectedRelationshipEmailLabel('<b>#legacy-user</b> - Legacy user', 'legacy@example.test'));
        assertRelationshipLabelHtml($label, 'legacy-user', '#legacy-user - Legacy user', email: 'legacy@example.test');
    }
});

it('tolerates missing optional attributes and respects non-join partial selects under strict model mode', function (string $scenario): void {
    RelationshipLabelUser::create(['id' => 'strict-user', 'name' => 'Strict user', 'email' => 'not-selected@example.test', 'profile_photo_url' => '/avatars/not-selected.svg']);
    if ($scenario === 'missing columns') {
        Schema::table('relationship_label_contacts', fn ($table) => $table->dropColumn(['email', 'profile_photo_url', 'portrait_url']));
    }
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $field = relationshipLabelSelect($scenario === 'partial select' ? RelationshipLabelPartialUser::class : RelationshipLabelUser::class);
        foreach (relationshipLabelVariants($field, 'strict-user', 'STRICT') as $label) {
            expect($label)->toBe('<b>#strict-user</b> - Strict user');
        }
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
})->with(['missing columns', 'partial select']);

it('applies boot-style UserColumn configureUsing avatar defaults to search selected and preloaded form choices without leaking', function (): void {
    RelationshipLabelUser::create([
        'id' => 'configured-user', 'name' => 'Configured user',
        'profile_photo_url' => '/avatars/default.svg', 'portrait_url' => '/portraits/configured.svg',
    ]);
    UserColumn::configureUsing(
        fn (UserColumn $column) => $column->avatarAttribute('portrait_url'),
        during: function (): void {
            foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'configured-user') as $label) {
                assertRelationshipLabelHtml($label, 'configured-user', '#configured-user - Configured user', '/portraits/configured.svg');
            }
        },
    );

    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'configured-user') as $label) {
        assertRelationshipLabelHtml($label, 'configured-user', '#configured-user - Configured user', '/avatars/default.svg');
    }
});

it('uses default or configured avatar accessors under strict missing-attribute mode', function (string $attribute, string $prefix): void {
    Schema::table('relationship_label_contacts', fn ($table) => $table->dropColumn(['email', 'profile_photo_url', 'portrait_url']));
    RelationshipLabelAccessorUser::create(['id' => 'accessor-avatar', 'name' => 'Accessor avatar']);
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        UserColumn::configureUsing(
            fn (UserColumn $column) => $column->avatarAttribute($attribute),
            during: function () use ($prefix): void {
                foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelAccessorUser::class), 'accessor-avatar') as $label) {
                    assertRelationshipLabelHtml($label, 'accessor-avatar', '#accessor-avatar - Accessor avatar', $prefix . 'accessor-avatar.svg', 'accessor@example.test');
                }
            },
        );
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
})->with([
    'default' => ['profile_photo_url', '/accessor-avatars/'],
    'configured' => ['portrait_url', '/accessor-portraits/'],
]);

it('sanitizes avatar URLs using native avatar classes and size without admitting injected nodes or attributes', function (string $url, string $expectedUrl): void {
    RelationshipLabelUser::create(['id' => 'safe-avatar', 'name' => 'Avatar <b>user</b>', 'profile_photo_url' => $url]);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'safe-avatar') as $label) {
        assertRelationshipLabelHtml($label, 'safe-avatar', '#safe-avatar - Avatar <b>user</b>', $expectedUrl);
    }
})->with([
    'https' => ['https://example.test/avatar.svg?a=1&b=2', 'https://example.test/avatar.svg?a=1&b=2'],
    'http' => ['http://example.test/avatar.svg', 'http://example.test/avatar.svg'],
    'relative' => ['../avatars/user.svg', '../avatars/user.svg'],
    'quoted attribute' => ['/avatar.svg?label=" onerror="alert(1)&next=\' onload=\'alert(2)', '/avatar.svg?label="%20onerror="alert(1)&next=\'%20onload=\'alert(2)'],
    'markup' => ['/avatar.svg?label="><script>alert(1)</script>', '/avatar.svg?label="><script>alert(1)</script>'],
]);

it('omits unsafe or blank avatars without generated or HasAvatar fallbacks', function (?string $url): void {
    RelationshipLabelUser::create(['id' => 'no-avatar', 'name' => 'No avatar', 'profile_photo_url' => $url, 'portrait_url' => '/portraits/not-a-fallback.svg']);
    foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'no-avatar') as $label) {
        expect($label)->toBe('<b>#no-avatar</b> - No avatar');
        assertRelationshipLabelHtml($label, 'no-avatar', '#no-avatar - No avatar');
    }
})->with([
    'null' => [null], 'empty' => [''], 'whitespace' => [" \t\n"],
    'javascript' => ['javascript:alert(1)'], 'mixed case' => ['JaVaScRiPt:alert(1)'],
    'leading whitespace' => [" \tjavascript:alert(1)"], 'control character' => ["java\nscript:alert(1)"],
    'data' => ['data:image/svg+xml,<svg onload="alert(1)"></svg>'],
    'file' => ['file:///etc/passwd'], 'ftp' => ['ftp://example.test/avatar.png'], 'vbscript' => ['vbscript:msgbox(1)'],
]);

it('does not fall back to the default avatar when a configured avatar is blank or nonexistent', function (string $attribute): void {
    RelationshipLabelUser::create(['id' => 'configured-empty', 'name' => 'Configured empty', 'profile_photo_url' => '/avatars/not-a-fallback.svg']);
    UserColumn::configureUsing(
        fn (UserColumn $column) => $column->avatarAttribute($attribute),
        during: function (): void {
            foreach (relationshipLabelVariants(relationshipLabelSelect(RelationshipLabelUser::class), 'configured-empty') as $label) {
                expect($label)->toBe('<b>#configured-empty</b> - Configured empty');
                assertRelationshipLabelHtml($label, 'configured-empty', '#configured-empty - Configured empty');
            }
        },
    );
})->with(['portrait_url', 'nonexistent_avatar_url']);

it('returns an empty server-side label for nullable unknown nonexistent and tampered selections without returning raw state', function (mixed $state): void {
    RelationshipLabelUser::create(['id' => 'known-user', 'name' => 'Known user']);
    $field = relationshipLabelSelect(RelationshipLabelUser::class)->state($state);

    expect($field->getOptionLabel())->toBe('')
        ->and($field->getState())->toBe($state === '' ? null : $state)
        ->and(RelationshipLabelUser::$avatarCalls)->toBe(0);
})->with([
    'nullable' => [null], 'empty' => [''], 'nonexistent key' => ['999999'],
    'unknown string' => ['unknown-owner'], 'tampered HTML' => ['<img src=x onerror="alert(1)">'],
]);

it('retains native validation for invalid nonempty selections despite blank display labels', function (string $state): void {
    $model = new RelationshipLabelRecord();
    $model->ownerModel = RelationshipLabelUser::class;
    $field = FormGenerator::makeForModel($model, relationManagerView: true)['owner_id'];
    $schema = FilamentSchema::make(new UserFieldListRecords())->model($model)->statePath('userFieldData')->components([$field]);
    $schema->fill(['owner_id' => $state]);

    expect(fn () => $schema->getState())->toThrow(ValidationException::class)
        ->and(RelationshipLabelRecord::count())->toBe(0);
})->with(['999999', 'unknown-owner', '<script>alert(1)</script>']);

it('preserves custom owner-key values selected state and persistence while displaying the actual model primary key', function (): void {
    $user = RelationshipLabelUser::create(['id' => 'actual-pk-73104', 'handle' => 'external-owner-key', 'name' => 'Custom owner', 'email' => 'owner@example.test']);
    $record = new RelationshipLabelRecord();
    $record->ownerModel = RelationshipLabelUser::class;
    $record->ownerKey = 'handle';
    $field = FormGenerator::makeForModel($record, relationManagerView: true)['owner_id'];
    $host = new UserFieldListRecords();
    $schema = FilamentSchema::make($host)->model($record)->statePath('userFieldData')->components([$field]);
    $schema->fill(['owner_id' => $user->handle]);

    foreach (relationshipLabelVariants($field, $user->handle, '73104') as $label) {
        expect($label)->toBe(expectedRelationshipEmailLabel('<b>#actual-pk-73104</b> - Custom owner', 'owner@example.test'));
        assertRelationshipLabelHtml($label, 'actual-pk-73104', '#actual-pk-73104 - Custom owner', email: 'owner@example.test');
    }
    expect($field->getSelectedRecord()->getKey())->toBe($user->getKey())
        ->and($field->getSearchResults('external-owner-key'))->toBe([]);
    $state = $schema->getState();
    expect($state)->toBe(['owner_id' => $user->handle]);
    $record->fill($state)->save();
    $schema->model($record)->saveRelationships();

    expect($record->refresh()->owner_id)->toBe($user->handle)
        ->and($record->owner->getKey())->toBe($user->getKey());
});

it('keeps source keys titles emails and avatars when a joined model scope does not select the base table', function (string $connection): void {
    if ($connection === 'secondary') {
        RelationshipOptionLabelFixtures::create($connection);
    }
    RelationshipLabelUser::on($connection)->create(['id' => 'source-73104', 'name' => 'Source user', 'email' => 'source@example.test', 'profile_photo_url' => '/avatars/source.svg']);
    RelationshipLabelUser::on($connection)->create(['id' => 'inactive-user', 'name' => 'Source user']);
    DB::connection($connection)->table('relationship_label_flags')->insert([
        ['id' => 'join-96308', 'contact_id' => 'source-73104', 'name' => 'Joined impostor', 'email' => 'joined@example.test', 'profile_photo_url' => '/avatars/joined.svg', 'active' => true],
        ['id' => 'join-inactive', 'contact_id' => 'inactive-user', 'name' => 'Joined impostor', 'email' => 'joined@example.test', 'profile_photo_url' => '/avatars/joined.svg', 'active' => false],
    ]);
    $record = (new RelationshipLabelRecord())->setConnection($connection);
    $field = relationshipLabelSelect(RelationshipLabelJoinedUser::class, $record);

    foreach (relationshipLabelVariants($field, 'source-73104', 'SOURCE') as $label) {
        assertRelationshipLabelHtml($label, 'source-73104', '#source-73104 - Source user', '/avatars/source.svg', 'source@example.test');
    }
    expect(array_keys($field->getSearchResults('source')))->toBe(['source-73104'])
        ->and(array_keys($field->getOptions()))->toBe(['source-73104'])
        ->and($field->getSelectedRecord()->getKey())->toBe('source-73104')
        ->and($field->getSearchResults('Joined impostor'))->toBe([])
        ->and($field->getSearchResults('joined@example'))->toBe([])
        ->and($field->getSearchResults('96308'))->toBe([]);
})->with(['unprefixed' => 'testing', 'prefixed' => 'secondary']);

it('leaves explicit native overrides and dictionary labels in their own plain formats', function (): void {
    RelationshipLabelUser::create(['id' => 'override-user', 'name' => 'Plain <b>override</b>']);
    $model = new RelationshipLabelRecord();
    $model->ownerModel = RelationshipLabelUser::class;
    $override = Select::make('owner_id')->relationship('owner', 'name')->searchable();
    $fields = FormGenerator::makeForModel($model, overwriteColumns: ['owner_id' => $override], relationManagerView: true);
    FilamentSchema::make(new UserFieldListRecords())->model($model)->statePath('userFieldData')->components($fields)->getFlatComponents(withHidden: true);
    $override->state('override-user');

    expect($fields['owner_id'])->toBe($override)
        ->and($override->isHtmlAllowed())->toBeFalse()
        ->and($override->getOptionLabel())->toBe('Plain <b>override</b>')
        ->and($override->getSearchResults('Plain'))->toBe(['override-user' => 'Plain <b>override</b>']);

    $dictionary = FormGenerator::makeForModel($model, enumDictionary: ['owner_id' => ['override-user' => 'Dictionary label']], relationManagerView: true)['owner_id'];
    expect($dictionary->hasRelationship())->toBeFalse()
        ->and($dictionary->isHtmlAllowed())->toBeFalse()
        ->and($dictionary->getOptions())->toBe(['override-user' => 'Dictionary label']);
});
