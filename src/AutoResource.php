<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoResource\Generators\FormGenerator;
use Miguilim\FilamentAutoResource\Generators\PageGenerator;
use Miguilim\FilamentAutoResource\Generators\TableGenerator;

class AutoResource extends Resource
{
    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static array $enumDictionary = [];

    protected static bool $intrusive = true;

    public static function tableExtra(Table $table): Table
    {
        return $table;
    }

    public static function getExtraPages(): array
    {
        return [];
    }

    public static function getPagesActions(): array
    {
        return [];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(FormGenerator::makeFormSchema(static::getModel(), static::$enumDictionary))
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        $finalTable = static::tableExtra($table);
        $hasSoftDeletes = method_exists(static::getModel(), 'bootSoftDeletes');

        $defaultFilters = [];
        $defaultActions = [Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()];
        $defaultBulkActions = [Tables\Actions\DeleteBulkAction::make()];

        if ($hasSoftDeletes) {
            $defaultFilters[] = Tables\Filters\TrashedFilter::make();

            $defaultActions[] = Tables\Actions\RestoreAction::make();

            $defaultBulkActions[] = Tables\Actions\RestoreBulkAction::make();
            $defaultBulkActions[] = Tables\Actions\ForceDeleteBulkAction::make();
        }

        $tableSchema = TableGenerator::makeTableSchema(static::getModel(), static::$visibleColumns, static::$searchableColumns, static::$enumDictionary);

        // Define automatic sort by column
        if ($finalTable->getDefaultSortColumn() === null) {
            $sortColumnsAvailable = collect($tableSchema)
            ->filter(fn ($column) => $column->isSortable())
            ->map(fn ($column) => $column->getName())
            ->values();

            $modelClass = static::getModel();
            $dummyModel = new $modelClass;

            if ($sortColumnsAvailable->contains('created_at')) {
                $finalTable = $finalTable->defaultSort('created_at', 'desc');
            } else if ($dummyModel->getIncrementing() && $sortColumnsAvailable->contains($dummyModel->getKeyName())) {
                $finalTable = $finalTable->defaultSort($dummyModel->getKeyName(), 'desc');
            } else if ($sortColumnsAvailable->contains('updated_at')) {
                $finalTable = $finalTable->defaultSort('updated_at', 'desc');
            }
        }

        return $finalTable
            ->columns($tableSchema)
            ->filters([...$finalTable->getFilters(), ...$defaultFilters])
            ->actions([...$finalTable->getActions(), ...$defaultActions])
            ->bulkActions([...$finalTable->getBulkActions(), ...$defaultBulkActions]);
    }

    public static function getPages(): array
    {
        return [...static::getExtraPages(), ...[
            'index' => PageGenerator::makeList(static::class),
            'create' => PageGenerator::makeCreate(static::class),
            'edit' => PageGenerator::makeEdit(static::class),
            'view' => PageGenerator::makeView(static::class),
        ]];
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
}
