<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;

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

        return $finalTable
            ->columns(FilamentAutoResourceHelper::makeTableSchema(static::getRelatedModel(), static::$visibleColumns));
    }
}
