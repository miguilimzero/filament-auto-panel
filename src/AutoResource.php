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
    public static array $visibleColumns = [];

    public static bool $simple = false;

    public static bool $intrusive = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(AutoResourceHelper::makeFormSchema(static::$model))
            ->columns(3);
    }

    public static function tableExtra(Table $table): Table
    {
        return $table;
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

            $defaultBulkActions[] = Tables\Actions\ForceDeleteBulkAction::make();
            $defaultBulkActions[] = Tables\Actions\RestoreBulkAction::make();
        }

        return $finalTable
            ->columns(AutoResourceHelper::makeTableSchema(static::$model, static::$visibleColumns))
            ->filters([...$finalTable->getFilters(), ...$defaultFilters])
            ->actions([...$finalTable->getActions(), ...$defaultActions])
            ->bulkActions([...$finalTable->getBulkActions(), ...$defaultBulkActions]);
    }

    public static function getPages(): array
    {
        if (self::$simple) {
            return [
                'index' => AutoResourceHelper::makeIndex(static::class),
            ];
        }
        
        return [
            'index' => AutoResourceHelper::makeList(static::class),
            'create' => AutoResourceHelper::makeCreate(static::class),
            'edit' => AutoResourceHelper::makeEdit(static::class),
            'view' => AutoResourceHelper::makeView(static::class),
        ];
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
}
