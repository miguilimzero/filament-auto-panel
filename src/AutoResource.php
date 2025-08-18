<?php

namespace Miguilim\FilamentAutoPanel;

use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Mounters\PageMounter;

class AutoResource extends Resource
{
    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $intrusive = true;

    protected static bool $readOnly = false;

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

    public static function getHeaderWidgets(): array
    {
        return [
            'list' => [],
            'view' => [],
        ];
    }

    public static function getFooterWidgets(): array
    {
        return [
            'list' => [],
            'view' => [],
        ];
    }

    public static function getColumnsOverwrite(): array
    {
        return [
            'table' => [],
            'form' => [],
            'infolist' => [],
        ];
    }

    public static function getExtraPages(): array
    {
        return [];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components(InfolistGenerator::make(
                modelClass: static::getModel(),
                overwriteColumns: static::getColumnsOverwriteMapped('infolist'),
                enumDictionary: static::$enumDictionary,
            ))
            ->columns(3);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(FormGenerator::make(
                modelClass: static::getModel(),
                overwriteColumns: static::getColumnsOverwriteMapped('form'),
                enumDictionary: static::$enumDictionary,
            ))
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        $hasSoftDeletes = method_exists(static::getModel(), 'bootSoftDeletes');

        $defaultFilters = [...static::getFilters()];
        $defaultTableActions = [...static::getTableActions(), ViewAction::make()];
        $defaultBulkActions = [...static::getBulkActions()];

        if (!static::$readOnly) {
            $defaultBulkActions[] = DeleteBulkAction::make();
        }

        if ($hasSoftDeletes) {
            $defaultFilters[] = TrashedFilter::make();

            if (!static::$readOnly) {
                $defaultTableActions[] = RestoreAction::make();
                $defaultBulkActions[] = RestoreBulkAction::make();
                $defaultBulkActions[] = ForceDeleteBulkAction::make();
            }
        }

        $tableSchema = TableGenerator::make(
            modelClass: static::getModel(),
            overwriteColumns: static::getColumnsOverwriteMapped('table'),
            enumDictionary: static::$enumDictionary,
            searchableColumns: static::$searchableColumns,
            visibleColumns: static::$visibleColumns
        );

        // Define automatic sort by column
        if ($table->getDefaultSortColumn() === null) {
            $sortColumnsAvailable = collect($tableSchema)
                ->filter(fn ($column) => $column->isSortable())
                ->map(fn ($column) => $column->getName())
                ->values();

            $modelClass = static::getModel();
            $dummyModel = new $modelClass;

            if ($dummyModel->getIncrementing() && $sortColumnsAvailable->contains($dummyModel->getKeyName())) {
                $table->defaultSort($dummyModel->getKeyName(), 'desc');
            } else if ($sortColumnsAvailable->contains('created_at')) {
                $table->defaultSort('created_at', 'desc');
            } else  if ($sortColumnsAvailable->contains('updated_at')) {
                $table->defaultSort('updated_at', 'desc');
            }
        }

        return $table
            ->columns($tableSchema)
            ->filters($defaultFilters)
            ->recordActions($defaultTableActions)
            ->toolbarActions($defaultBulkActions);
    }

    public static function getPages(): array
    {
        $pages = [...static::getExtraPages(), ...[
            'index'  => PageMounter::makeList(static::class),
            'view'   => PageMounter::makeView(static::class),
        ]];

        return $pages;
    }

    public static function getEloquentQuery(): Builder
    {
        $parent = parent::getEloquentQuery();

        if (method_exists(static::getModel(), 'bootSoftDeletes')) {
            $parent = $parent->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        return $parent;
    }

    public static function getIntrusive(): bool
    {
        return static::$intrusive;
    }

    public static function getReadOnly(): bool
    {
        return static::$readOnly;
    }

    public static function getBulkActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnBulkAction)
            ->map(fn (AutoAction $action) => $action->bulk()->accessSelectedRecords())
            ->all();
    }

    public static function getTableActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnTable)
            ->all();
    }

    public static function getViewPageActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnViewPage)
            ->all();
    }

    public static function getListPageActions(): array
    {
        return collect(static::getActions())
            ->filter(fn (AutoAction $action) => $action->showOnListPage)
            ->all();
    }

    protected static function getColumnsOverwriteMapped(string $type): array
    {
        return collect(static::getColumnsOverwrite()[$type])
            ->mapWithKeys(fn ($column) => [$column->getName() => $column])
            ->all();
    }
}
