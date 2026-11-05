<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;


class TableDefaults
{
    public static function sort(Table $table, Model $model): Table
    {
        if ($table->getDefaultSortColumn() !== null || $table->getDefaultSortQuery() !== null) {
            return $table;
        }

        $sortable = [];

        foreach ($table->getColumns() as $column) {
            if ($column->isSortable()) {
                $sortable[] = $column->getName();
            }
        }

        $candidates = [
            $model->getIncrementing() ? $model->getKeyName() : null,
            $model->usesTimestamps() ? $model->getCreatedAtColumn() : null,
            $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null,
        ];

        foreach ($candidates as $column) {
            if ($column !== null && in_array($column, $sortable, true)) {
                return $table->defaultSort($column, 'desc');
            }
        }

        return $table;
    }
}
