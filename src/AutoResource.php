<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

class AutoResource extends Resource
{
    public static array $tableColumns = [];

    public static function form(Form $form): Form
    {
        return $form
            ->schema(AutoResourceHelper::makeFormSchema(static::$model));
    }

    public static function tableOthers(Table $table): Table
    {
        return $table;
    }

    public static function table(Table $table): Table
    {
        return static::tableOthers($table->columns(AutoResourceHelper::makeTableSchema(static::$model, static::$tableColumns)));
    }

    public static function getPages(): array
    {
        return [
            'index' => AutoResourceHelper::makeIndex(static::class),
        ];
    }
}
