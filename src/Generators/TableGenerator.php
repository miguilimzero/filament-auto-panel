<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types;
use Filament\Facades\Filament;
use Filament\Support\Components\ViewComponent;
use Filament\Tables;
use Filament\Tables\Columns\Column as TableColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
 
class TableGenerator extends AbstractGenerator
{
    protected array $visibleColumns;

    protected array $searchableColumns;

    public function visibleColumns(array $visibleColumns)
    {
        $this->visibleColumns = $visibleColumns;

        return $this;
    }

    public function searchableColumns(array $searchableColumns)
    {
        $this->searchableColumns = $searchableColumns;

        return $this;
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Tables\Columns\TextColumn::make("{$relationshipName}.{$relationshipTitleColumnName}")
            ->weight('bold')
            ->color('primary')
            ->url(function ($record) use ($column) {
                if ($record === null) {
                    return null;
                }
            
                $selectedResource = null;
                $relationship = Str::before($column->getName(), '.');
                $relatedRecord = $record->{$relationship};
            
                if ($relatedRecord === null) {
                    return null;
                }
            
                foreach (Filament::getResources() as $resource) {
                    if ($relatedRecord instanceof ($resource::getModel())) {
                        $selectedResource = $resource;
                    
                        break;
                    }
                }

                if ($selectedResource === null) {
                    return null;
                }
            
                return $selectedResource::getUrl('view', [$relatedRecord->getKey()]);
            });
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return Tables\Columns\TextColumn::make($column->getName())
            ->badge()
            ->formatStateUsing(function($state) use($dictionary) {
                $finalFormat = $dictionary[$state] ?? $state;
    
                return (is_array($finalFormat)) ? $finalFormat[0] : $finalFormat;
            })->color(function($state) use($dictionary) {
                if (! is_array($dictionary[$state]) || ! array_key_exists(1, $dictionary[$state])) {
                    return null;
                }
    
                return $dictionary[$state][1];
            });
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        return Tables\Columns\TextColumn::make($column->getName())
            ->date($column->getType() instanceof Types\DateType)
            ->dateTime($column->getType() instanceof Types\DateTimeType);
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return Tables\Columns\IconColumn::make($column->getName());
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return Tables\Columns\TextColumn::make($column->getName())
            ->wrap();
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if (Str::of($column->getName())->contains(['link', 'url'])) {
            return Tables\Columns\TextColumn::make($column->getName())
                ->url(fn($record) => $record->{$column->getName()})
                ->color('primary')
                ->openUrlInNewTab();
        }

        return Tables\Columns\TextColumn::make($column->getName())
            ->sortable($this->isNumericColumn($column))
            ->searchable($this->modelInstance->getKeyName() === $column->getName());
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        return collect($this->getResourceColumns($exceptColumns, $overwriteColumns, $enumDictionary))->map(function(TableColumn $columnInstance) {
            return $columnInstance
                ->searchable($columnInstance->isSearchable() ?: in_array($columnInstance->getName(), $this->searchableColumns))
                ->toggleable(
                    isToggledHiddenByDefault: !empty($this->visibleColumns) && ! in_array($columnInstance->getName(), $this->visibleColumns)
                );
        })->all();
    }
}