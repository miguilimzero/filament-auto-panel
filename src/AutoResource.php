<?php

namespace Miguilim\FilamentAutoPanel;

use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoPanel\Concerns\HasAutoPanelConfiguration;
use Miguilim\FilamentAutoPanel\Filament\RelationManagers\UserSessionsRelationManager;
use Miguilim\FilamentAutoPanel\Generators\FilterGenerator;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;
use Miguilim\FilamentAutoPanel\Support\Actions;
use Miguilim\FilamentAutoPanel\Support\TableDefaults;
use Miguilim\FilamentAutoPanel\Support\UserSessions;

class AutoResource extends Resource
{
    use HasAutoPanelConfiguration;

    public static function getFilters(): array
    {
        return [];
    }

    public static function getActions(): array
    {
        return [];
    }

    public static function getTabs(): array
    {
        return [];
    }

    public static function getRelations(): array
    {
        return static::withAutomaticRelations(parent::getRelations());
    }

    public static function withAutomaticRelations(array $relations): array
    {
        if (UserSessions::supportsModel(static::getModel()) && ! static::hasSessionsRelationManager($relations)) {
            $relations[] = UserSessionsRelationManager::class;
        }

        return $relations;
    }

    protected static function hasSessionsRelationManager(array $relations): bool
    {
        foreach ($relations as $relation) {
            if ($relation instanceof RelationGroup) {
                $group = (clone $relation)->ownerRecord(null)->pageClass(null);

                if (static::hasSessionsRelationManager($group->getManagers())) {
                    return true;
                }

                continue;
            }

            $class = $relation instanceof RelationManagerConfiguration ? $relation->relationManager : $relation;

            if (is_string($class) && is_a($class, UserSessionsRelationManager::class, true)) {
                return true;
            }
        }

        return false;
    }

    public static function getHeaderWidgets(): array
    {
        return ['list' => [], 'view' => []];
    }

    public static function getFooterWidgets(): array
    {
        return ['list' => [], 'view' => []];
    }

    public static function getColumnsOverwrite(): array
    {
        return ['table' => [], 'form' => [], 'infolist' => []];
    }

    public static function getExtraPages(): array
    {
        return [];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components(InfolistGenerator::make(...static::getSchemaOptions('infolist'), modelClass: static::getModel()))
            ->columns(3);
    }

    public static function form(Schema $schema): Schema
    {
        $modelClass = static::getModel();
        $model = $schema->getModelInstance() ?? new $modelClass();

        return $schema
            ->components(FormGenerator::makeForModel(...static::getSchemaOptions('form'), model: $model))
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        $modelClass = static::getModel();
        $filters = [
            ...FilterGenerator::makeForTable($table, new $modelClass(), enumDictionary: static::getEnumDictionary()),
            ...static::getFilters(),
        ];

        if (static::hasSoftDeletes()) {
            $filters[] = TrashedFilter::make();
        }

        $bulkActions = static::getBulkActions();

        $table
            ->columns(TableGenerator::make(...static::getSchemaOptions('table'), modelClass: static::getModel()))
            ->filters($filters)
            ->filtersFormWidth(Width::ThreeExtraLarge)
            ->recordActions(static::getTableActions())
            ->toolbarActions($bulkActions ? [BulkActionGroup::make($bulkActions)] : []);

        $model = static::getModel();

        return TableDefaults::sort($table, new $model());
    }

    public static function getPages(): array
    {
        return [
            'index' => PageMounter::makeList(static::class),
            'view' => PageMounter::makeView(static::class),
            ...static::getExtraPages(),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::hasSoftDeletes() ? $query->withoutGlobalScope(SoftDeletingScope::class) : $query;
    }

    public static function isIntrusive(): bool
    {
        return static::$intrusive;
    }

    public static function isReadOnly(): bool
    {
        return static::$readOnly;
    }

    public static function getBulkActions(): array
    {
        return Actions::merge(
            Actions::select(static::getActions(), 'showOnBulkAction'),
            Actions::bulk(static::isReadOnly(), static::hasSoftDeletes()),
        );
    }

    public static function getTableActions(): array
    {
        return Actions::merge(
            Actions::select(static::getActions(), 'showOnTable'),
            Actions::table(static::isReadOnly(), static::hasSoftDeletes()),
        );
    }

    public static function getViewPageActions(): array
    {
        return Actions::merge(
            Actions::select(static::getActions(), 'showOnViewPage'),
            Actions::viewing(static::isReadOnly(), static::hasSoftDeletes()),
        );
    }

    public static function getListPageActions(): array
    {
        return Actions::merge(
            Actions::select(static::getActions(), 'showOnListPage'),
            Actions::listing(static::isReadOnly()),
        );
    }

    public static function getColumnsOverwriteMapped(string $type): array
    {
        return static::mapColumnOverrides(static::getColumnsOverwrite()[$type] ?? []);
    }

    /** Shared with resource-backed relation managers, including dynamic dictionary overrides. */
    public static function getSchemaOptions(string $type): array
    {
        $options = [
            'overwriteColumns' => static::getColumnsOverwriteMapped($type),
            'enumDictionary' => static::getEnumDictionary(),
        ];

        if ($type === 'table') {
            $options['visibleColumns'] = static::$visibleColumns;
            $options['searchableColumns'] = static::$searchableColumns;
        }

        return $options;
    }

    protected static function getEnumDictionary(): array
    {
        return static::$enumDictionary;
    }

    protected static function hasSoftDeletes(): bool
    {
        return method_exists(static::getModel(), 'bootSoftDeletes');
    }
}
