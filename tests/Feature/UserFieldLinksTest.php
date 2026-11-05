<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema as FilamentSchema;
use Filament\Tables\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17Identity;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17ListRecords;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17Record;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17UserFieldFixtures;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17UserLinkResource;
use Miguilim\FilamentAutoPanel\Tests\Fixtures\Issue17UserLinkWithoutViewResource;
use Zvizvi\UserFields\Components\UserColumn;
use Zvizvi\UserFields\Components\UserEntry;

require_once __DIR__ . '/../Fixtures/Issue17UserFieldFixtures.php';
require_once __DIR__ . '/../Fixtures/Issue17UserLinkFixtures.php';

beforeEach(function (): void {
    Issue17UserFieldFixtures::create();
    Issue17UserLinkResource::$accessible = true;
});

afterEach(function (): void {
    Issue17UserLinkResource::$accessible = true;
    Issue17UserFieldFixtures::drop();
});

/** @param class-string<AutoResource> $resource */
function issue17RegisterUserLinkResource(string $resource): Panel
{
    $panel = Panel::make()->id('issue17-links')->path('issue17-admin')->resources([$resource]);
    Filament::setCurrentPanel($panel);

    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->group(fn () => $resource::registerRoutes($panel));
    Route::getRoutes()->refreshNameLookups();

    return $panel;
}

function issue17UserLinkComponents(): array
{
    $otherUser = Issue17Identity::create(['id' => 101, 'name' => 'Not the author']);
    $author = Issue17Identity::create(['id' => 202, 'name' => 'Profile link author']);
    $record = Issue17Record::create(['id' => 303, 'user_id' => $otherUser->id, 'author_id' => $author->id]);

    expect($record->getKey())->not->toBe($author->getKey())
        ->and($record->user_id)->not->toBe($author->getKey());

    $column = TableGenerator::make(Issue17Record::class)['author_id'];
    $host = new Issue17ListRecords();
    $table = Table::make($host)->query(Issue17Record::query())->columns([$column]);
    $host->setIssue17Table($table);
    $table->getColumns();
    $column->record($record);

    $schema = FilamentSchema::make(new Issue17ListRecords())
        ->model($record)
        ->components(InfolistGenerator::make(Issue17Record::class));
    $entry = collect($schema->getFlatComponents(withHidden: true))
        ->first(fn ($component): bool => $component instanceof UserEntry && $component->getName() === 'author.name');

    expect($column)->toBeInstanceOf(UserColumn::class)
        ->and($column->getName())->toBe('author.name')
        ->and($entry)->toBeInstanceOf(UserEntry::class);

    return [$column, $entry, $record];
}

it('links user columns and entries to the actual related user view route', function (): void {
    $panel = issue17RegisterUserLinkResource(Issue17UserLinkResource::class);
    [$column, $entry, $record] = issue17UserLinkComponents();
    $routeName = Issue17UserLinkResource::getRouteBaseName($panel) . '.view';
    $expected = Issue17UserLinkResource::getUrl('view', ['record' => $record->author]);

    expect($routeName)->toBe('filament.issue17-links.resources.issue17-users.view')
        ->and(Route::has($routeName))->toBeTrue()
        ->and($expected)->not->toBe(Issue17UserLinkResource::getUrl('view', ['record' => $record]))
        ->and($expected)->not->toBe(Issue17UserLinkResource::getUrl('view', ['record' => $record->user]));

    foreach ([$column, $entry] as $component) {
        expect($component->getUrl())->toBe($expected);
        $route = Route::getRoutes()->match(Request::create($component->getUrl()));
        expect($route->getName())->toBe($routeName)
            ->and($route->parameter('record'))->toBe((string) $record->author->getRouteKey());
    }
});

it('does not link user columns or entries to an inaccessible user resource', function (): void {
    $panel = issue17RegisterUserLinkResource(Issue17UserLinkResource::class);
    Issue17UserLinkResource::$accessible = false;
    [$column, $entry] = issue17UserLinkComponents();

    expect(Filament::getResources())->toContain(Issue17UserLinkResource::class)
        ->and(Issue17UserLinkResource::canAccess())->toBeFalse()
        ->and(Issue17UserLinkResource::hasPage('view'))->toBeTrue()
        ->and(Route::has(Issue17UserLinkResource::getRouteBaseName($panel) . '.view'))->toBeTrue()
        ->and($column->getUrl())->toBeNull()
        ->and($entry->getUrl())->toBeNull();
});

it('does not link user columns or entries when the user resource has no view page', function (): void {
    $panel = issue17RegisterUserLinkResource(Issue17UserLinkWithoutViewResource::class);
    [$column, $entry] = issue17UserLinkComponents();
    $routeName = Issue17UserLinkWithoutViewResource::getRouteBaseName($panel);

    expect(Filament::getResources())->toContain(Issue17UserLinkWithoutViewResource::class)
        ->and(Issue17UserLinkWithoutViewResource::canAccess())->toBeTrue()
        ->and(Issue17UserLinkWithoutViewResource::hasPage('view'))->toBeFalse()
        ->and(Route::has($routeName . '.index'))->toBeTrue()
        ->and(Route::has($routeName . '.view'))->toBeFalse()
        ->and($column->getUrl())->toBeNull()
        ->and($entry->getUrl())->toBeNull();
});
