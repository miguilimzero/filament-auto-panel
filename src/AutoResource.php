<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AutoResource extends Resource
{
    protected static array $visibleColumns = [];

    protected static bool $intrusive = true;

    public static function tableExtra(Table $table): Table
    {
        return $table;
    }

    public static function getPagesExtra(): array
    {
        return [];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(FilamentAutoResourceHelper::makeFormSchema(static::getModel()))
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

        return $finalTable
            ->columns(FilamentAutoResourceHelper::makeTableSchema(static::getModel(), static::$visibleColumns))
            ->filters([...$finalTable->getFilters(), ...$defaultFilters])
            ->actions([...$finalTable->getActions(), ...$defaultActions])
            ->bulkActions([...$finalTable->getBulkActions(), ...$defaultBulkActions]);
    }

    public static function getPages(): array
    {
        return [...static::getPagesExtra(), ...[
            'index' => FilamentAutoResourceHelper::makeList(static::class),
            'create' => FilamentAutoResourceHelper::makeCreate(static::class),
            'edit' => FilamentAutoResourceHelper::makeEdit(static::class),
            'view' => FilamentAutoResourceHelper::makeView(static::class),
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
