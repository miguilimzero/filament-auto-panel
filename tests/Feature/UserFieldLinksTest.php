<?php

use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldIdentity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldRecord;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldLinkResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\UserFieldLinkWithoutViewResource;

require_once __DIR__ . '/../Fixtures/UserFieldFixtures.php';
require_once __DIR__ . '/../Fixtures/UserFieldLinkFixtures.php';

beforeEach(function (): void {
    UserFieldFixtures::create();
    UserFieldIdentity::$avatarCalls = 0;
    UserFieldLinkResource::$accessible = true;
});

afterEach(function (): void {
    UserFieldLinkResource::$accessible = true;
    UserFieldFixtures::drop();
});

/** @param class-string<AutoResource> $resource */
function registerUserFieldLinkResource(string $resource): Panel
{
    $panel = Panel::make()->id('user-field-links')->path('user-field-admin')->resources([$resource]);
    Filament::setCurrentPanel($panel);

    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(fn () => $resource::registerRoutes($panel));
    Route::getRoutes()->refreshNameLookups();

    return $panel;
}

function userFieldLinkComponents(): array
{
    $otherUser = UserFieldIdentity::create(['id' => 101, 'name' => 'Not the author']);
    $author = UserFieldIdentity::create(['id' => 202, 'name' => 'Profile link <b>author</b>', 'profile_photo_url' => '/avatars/link-author.svg']);
    $record = UserFieldRecord::create(['id' => 303, 'user_id' => $otherUser->id, 'author_id' => $author->id]);

    expect($record->getKey())->not->toBe($author->getKey())
        ->and($record->user_id)->not->toBe($author->getKey());

    $column = TableGenerator::make(UserFieldRecord::class)['author_id'];
    $host = new UserFieldListRecords();
    $table = Table::make($host)->query(UserFieldRecord::query())->columns([$column]);
    $host->setUserFieldTable($table);
    $table->getColumns();
    $column->record($record);

    $schema = FilamentSchema::make(new UserFieldListRecords())
        ->model($record)
        ->components(InfolistGenerator::make(UserFieldRecord::class));
    $entry = collect($schema->getFlatComponents(withHidden: true))
        ->first(fn ($component): bool => $component instanceof TextEntry && $component->getName() === 'author.name');

    expect($column::class)->toBe(UserColumn::class)
        ->and($column->getName())->toBe('author.name')
        ->and($entry::class)->toBe(TextEntry::class);

    return [$column, $entry, $record];
}

it('links user columns and entries to the actual related user view route', function (): void {
    $panel = registerUserFieldLinkResource(UserFieldLinkResource::class);
    [$column, $entry, $record] = userFieldLinkComponents();
    $routeName = UserFieldLinkResource::getRouteBaseName($panel) . '.view';
    $expected = UserFieldLinkResource::getUrl('view', ['record' => $record->author]);

    expect($routeName)->toBe('filament.user-field-links.resources.user-field-users.view')
        ->and(Route::has($routeName))->toBeTrue()
        ->and($expected)->not->toBe(UserFieldLinkResource::getUrl('view', ['record' => $record]))
        ->and($expected)->not->toBe(UserFieldLinkResource::getUrl('view', ['record' => $record->user]));

    foreach ([$column, $entry] as $component) {
        expect($component->getUrl())->toBe($expected);
        $route = Route::getRoutes()->match(Request::create($component->getUrl()));
        expect($route->getName())->toBe($routeName)
            ->and($route->parameter('record'))->toBe((string) $record->author->getRouteKey());

        $html = $component === $column ? $column->renderInLayout()->toHtml() : $entry->toHtml();
        $document = new DOMDocument();
        expect($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();
        $dom = new DOMXPath($document);
        $links = $dom->query('//a[@href]');
        expect($links->length)->toBe(1)
            ->and($links->item(0)->getAttribute('href'))->toBe($expected)
            ->and($links->item(0)->textContent)->toContain($record->author->name)
            ->and($dom->query('//b')->length)->toBe(0)
            ->and($dom->query('//img')->length)->toBe(1)
            ->and($dom->query('//a//img')->length)->toBe(1)
            ->and($dom->query('//img')->item(0)->getAttribute('src'))->toBe('/avatars/link-author.svg');
    }
    expect(UserFieldIdentity::$avatarCalls)->toBe(0);
});

it('does not link user columns or entries to an inaccessible user resource', function (): void {
    $panel = registerUserFieldLinkResource(UserFieldLinkResource::class);
    UserFieldLinkResource::$accessible = false;
    [$column, $entry] = userFieldLinkComponents();

    expect(Filament::getResources())->toContain(UserFieldLinkResource::class)
        ->and(UserFieldLinkResource::canAccess())->toBeFalse()
        ->and(UserFieldLinkResource::hasPage('view'))->toBeTrue()
        ->and(Route::has(UserFieldLinkResource::getRouteBaseName($panel) . '.view'))->toBeTrue()
        ->and($column->getUrl())->toBeNull()
        ->and($entry->getUrl())->toBeNull();

    foreach ([$column, $entry] as $component) {
        $html = $component === $column ? $column->renderInLayout()->toHtml() : $entry->toHtml();
        $document = new DOMDocument();
        expect($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();
        $dom = new DOMXPath($document);
        expect($dom->query('//a[@href]')->length)->toBe(0)
            ->and($dom->query('//img')->length)->toBe(1)
            ->and($dom->query('//img')->item(0)->getAttribute('src'))->toBe('/avatars/link-author.svg')
            ->and($document->textContent)->toContain('Profile link <b>author</b>')
            ->and($dom->query('//b')->length)->toBe(0);
    }
    expect(UserFieldIdentity::$avatarCalls)->toBe(0);
});

it('does not link user columns or entries when the user resource has no view page', function (): void {
    $panel = registerUserFieldLinkResource(UserFieldLinkWithoutViewResource::class);
    [$column, $entry] = userFieldLinkComponents();
    $routeName = UserFieldLinkWithoutViewResource::getRouteBaseName($panel);

    expect(Filament::getResources())->toContain(UserFieldLinkWithoutViewResource::class)
        ->and(UserFieldLinkWithoutViewResource::canAccess())->toBeTrue()
        ->and(UserFieldLinkWithoutViewResource::hasPage('view'))->toBeFalse()
        ->and(Route::has($routeName . '.index'))->toBeTrue()
        ->and(Route::has($routeName . '.view'))->toBeFalse()
        ->and($column->getUrl())->toBeNull()
        ->and($entry->getUrl())->toBeNull();

    foreach ([$column, $entry] as $component) {
        $html = $component === $column ? $column->renderInLayout()->toHtml() : $entry->toHtml();
        $document = new DOMDocument();
        expect($document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING))->toBeTrue();
        $dom = new DOMXPath($document);
        expect($dom->query('//a[@href]')->length)->toBe(0)
            ->and($dom->query('//img')->length)->toBe(1)
            ->and($dom->query('//img')->item(0)->getAttribute('src'))->toBe('/avatars/link-author.svg')
            ->and($document->textContent)->toContain('Profile link <b>author</b>')
            ->and($dom->query('//b')->length)->toBe(0);
    }
    expect(UserFieldIdentity::$avatarCalls)->toBe(0);
});
