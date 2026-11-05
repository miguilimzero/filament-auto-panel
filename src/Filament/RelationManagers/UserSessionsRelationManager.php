<?php

namespace Miguilim\FilamentAutoPanel\Filament\RelationManagers;

use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Filament\Actions\RevokeSessionAction;
use Miguilim\FilamentAutoPanel\Models\DatabaseSession;
use Miguilim\FilamentAutoPanel\Support\UserSessions;
use Throwable;

class UserSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'Sessions';

    protected ?Model $authorizedSessionOwner = null;

    protected bool $canManageSessions = false;

    public function mount(): void
    {
        $this->authorizeOwner();

        parent::mount();
    }

    public function hydrate(): void
    {
        // Filament 5.0 does not reauthorize relation managers on subsequent requests.
        $this->authorizeOwner();
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::authorizedOwner($ownerRecord, $pageClass) !== null;
    }

    protected static function authorizedOwner(Model $owner, string $pageClass): ?Model
    {
        try {
            $panel = Filament::getCurrentPanel();
            $resource = static::ownerResource($pageClass);

            if (! $panel || ! $resource || ! Auth::guard($panel->getAuthGuard())->check()
                || (! in_array($resource, $panel->getResources(), true)
                    && (! method_exists(Filament::getFacadeRoot(), 'getResourceConfiguration')
                        || Filament::getResourceConfiguration($resource) === null))
                || $owner::class !== $resource::getModel()
                || ! UserSessions::supportsModel($owner::class)
                || ! $owner instanceof Authenticatable || ! $owner->exists
                || $owner->getKey() === null) {
                return null;
            }

            $identifier = $owner->getAuthIdentifier();

            if ((! is_string($identifier) && ! is_int($identifier)) || (string) $identifier === '') {
                return null;
            }

            $freshOwner = $resource::getEloquentQuery()->whereKey($owner->getKey())->first();

            if (! $freshOwner instanceof Authenticatable || ! $freshOwner instanceof Model
                || (string) $freshOwner->getAuthIdentifier() !== (string) $identifier
                || ! $resource::canView($freshOwner)) {
                return null;
            }

            $session = UserSessions::newModel();
            $schema = $session->getConnection()->getSchemaBuilder();

            return $schema->hasTable($session->getTable())
                && $schema->hasColumns($session->getTable(), UserSessions::METADATA_COLUMNS)
                    ? $freshOwner
                    : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function ownerResource(string $pageClass): ?string
    {
        if (! is_subclass_of($pageClass, Page::class)) {
            return null;
        }

        $resource = $pageClass::getResource();

        return is_subclass_of($resource, AutoResource::class) ? $resource : null;
    }

    protected function authorizeOwner(): void
    {
        $owner = isset($this->ownerRecord) && is_string($this->pageClass)
            ? static::authorizedOwner($this->ownerRecord, $this->pageClass)
            : null;

        abort_unless($owner !== null, 403);

        $this->authorizedSessionOwner = $owner;
        $resource = static::ownerResource($this->pageClass ?? '');
        $this->canManageSessions = ! $resource::isReadOnly() && $resource::canEdit($owner);
    }

    public function isReadOnly(): bool
    {
        try {
            $resource = static::ownerResource($this->pageClass ?? '');

            return ! $resource || $resource::isReadOnly();
        } catch (Throwable) {
            return true;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->relationship(null)
            ->query(fn (): Builder => UserSessions::queryFor($this->getOwnerRecord()))
            ->modelLabel('session')
            ->pluralModelLabel('sessions')
            ->recordTitle('Session')
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->label('User agent')
                    ->wrap()
                    ->limit(100),
                TextColumn::make('last_activity')
                    ->label('Last active')
                    ->state(fn (DatabaseSession $record): Carbon => Carbon::createFromTimestamp($record->last_activity))
                    ->since()
                    ->sortable(),

            ])
            ->defaultSort('last_activity', 'desc')
            ->paginationMode(PaginationMode::Default)
            ->paginated([10, 25, 50])
            ->selectable(false)
            ->reorderable(null, false)
            ->authorizeReorder(false)
            ->headerActions([])
            ->toolbarActions([])
            ->bulkActions([])
            ->recordActions([
                RevokeSessionAction::make()
                    ->label('Revoke session')
                    ->model(DatabaseSession::class)
                    ->recordTitle('Session')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (?Model $record = null): bool => $this->canRevokeSession($record))

                    ->action(function (?Model $record = null): void {
                        $this->revokeSession($record);
                    }),
            ]);
    }

    // Native selection endpoints can return raw IDs even when the UI is not selectable.
    public function getAllSelectableTableRecordKeys(): array
    {
        return [];
    }

    public function getGroupedSelectableTableRecordKeys(?string $group): array
    {
        return [];
    }

    public function getSelectedTableRecords(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Collection
    {
        return collect();
    }

    public function getTableRecordsPerPage(): int
    {
        return in_array($this->tableRecordsPerPage, [10, 25, 50, '10', '25', '50'], true)
            ? (int) $this->tableRecordsPerPage
            : 10;
    }

    public function getTableRecordKey(Model | array $record): string
    {
        if (! $record instanceof DatabaseSession) {
            throw new LogicException('Only database session records can be used in the sessions table.');
        }

        return UserSessions::recordKey($record, $this->getOwnerRecord());
    }

    protected function resolveTableRecord(?string $key): Model | array | null
    {
        if ($key === null || ! preg_match('/\A[0-9a-f]{64}\z/', $key)
            || ! isset($this->ownerRecord) || ! is_string($this->pageClass)
            || ! static::canViewForRecord($this->ownerRecord, $this->pageClass)) {
            return null;
        }

        foreach (UserSessions::queryFor($this->getOwnerRecord())->get() as $session) {
            if (hash_equals($this->getTableRecordKey($session), $key)) {
                return $session;
            }
        }

        return null;
    }


    protected function canRevokeSession(?Model $record): bool
    {
        return $record instanceof DatabaseSession
            && $this->authorizedSessionOwner instanceof Authenticatable
            && $this->canManageSessions
            && (string) $record->user_id === (string) $this->authorizedSessionOwner->getAuthIdentifier();
    }

    protected function revokeSession(?Model $record): void
    {
        $owner = static::authorizedOwner($this->getOwnerRecord(), $this->pageClass ?? '');
        $resource = static::ownerResource($this->pageClass ?? '');

        abort_unless($owner && $resource && ! $resource::isReadOnly() && $resource::canEdit($owner)
            && $record instanceof DatabaseSession, 403);

        $deleted = UserSessions::queryFor($owner)
            ->whereKey($record->getKey())

            ->delete();

        abort_unless($deleted > 0, 404);

        $this->flushCachedTableRecords();
    }
}
