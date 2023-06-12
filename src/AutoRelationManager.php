<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;

class AutoRelationManager extends RelationManager
{
    public static array $visibleColumns = [];

    public static bool $intrusive = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(FilamentAutoResourceHelper::makeFormSchema(static::getRelatedModel()))
            ->columns(3);
    }
    
    public static function tableExtra(Table $table): Table
    {
        return $table;
    }

    public static function table(Table $table): Table
    {
        $finalTable = static::tableExtra($table);
        $hasSoftDeletes = method_exists(static::getRelatedModel(), 'bootSoftDeletes');

        $defaultFilters = [];
        $defaultHeaderActions = [Tables\Actions\CreateAction::make()];
        $defaultActions = [Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()];
        $defaultBulkActions = [Tables\Actions\DeleteBulkAction::make()];

        if ($hasSoftDeletes) {
            $defaultFilters[] = Tables\Filters\TrashedFilter::make();

            $defaultActions[] = Tables\Actions\RestoreAction::make();

            $defaultBulkActions[] = Tables\Actions\ForceDeleteBulkAction::make();
            $defaultBulkActions[] = Tables\Actions\RestoreBulkAction::make();
        }

        return $finalTable
            ->columns(FilamentAutoResourceHelper::makeTableSchema(static::getRelatedModel(), static::$visibleColumns))
            ->filters([...$finalTable->getFilters(), ...$defaultFilters])
            ->headerActions([...$finalTable->getHeaderActions(), ...$defaultHeaderActions])
            ->actions([...$finalTable->getActions(), ...$defaultActions])
            ->bulkActions([...$finalTable->getBulkActions(), ...$defaultBulkActions]);
    }

    protected function getTableQuery(): Builder
    {
        $parent = parent::getTableQuery();

        if (method_exists(static::getRelatedModel(), 'bootSoftDeletes')) {
            $parent->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        return $parent;
    }
}
