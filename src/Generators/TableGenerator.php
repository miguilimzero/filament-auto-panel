<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Support\Components\ViewComponent;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Columns\Column as TableColumn;
use Illuminate\Support\Str;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

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

    public static function make(
        string $modelClass,
        array $exceptColumns = [],
        array $overwriteColumns = [],
        array $enumDictionary = [],
        array $visibleColumns = [],
        array $searchableColumns = []
    ): array {
        return static::getCachedSchema(
            parameters: func_get_args(),
            function: fn () => (new static($modelClass))->visibleColumns($visibleColumns)
                ->searchableColumns($searchableColumns)
                ->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary)
        );
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Tables\Columns\TextColumn::make("{$relationshipName}.{$relationshipTitleColumnName}")
            ->placeholder(fn () => $this->placeholderHtml())
            ->weight(FontWeight::Bold)
            ->color('primary')
            ->url(function ($record) use ($relationshipName) {
                if ($record === null) {
                    return null;
                }

                $relatedRecord = $record->{$relationshipName};

                if ($relatedRecord === null) {
                    return null;
                }

                $selectedResource = static::tryToGuessRelatedResource($relatedRecord);

                if ($selectedResource === null) {
                    return null;
                }

                return $selectedResource::getUrl('view', [$record->{$relationshipName}->getKey()]);
            });
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return Tables\Columns\TextColumn::make($column->getName())
            ->badge()
            ->formatStateUsing(function ($state) use ($dictionary) {
                $finalFormat = $dictionary[$state] ?? $state;

                return (is_array($finalFormat)) ? $finalFormat[0] : $finalFormat;
            })->color(function ($state) use ($dictionary) {
                if (! is_array($dictionary[$state]) || ! array_key_exists(1, $dictionary[$state])) {
                    return 'primary';
                }

                return $dictionary[$state][1];
            });
    }

    protected function handleArrayColumn(Column $column): ViewComponent
    {
        return Tables\Columns\TextColumn::make($column->getName())
            ->badge()
            ->placeholder(fn () => $this->placeholderHtml());
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $textColumn = Tables\Columns\TextColumn::make($column->getName())
            ->sortable()
            ->placeholder(fn () => $this->placeholderHtml());

        return ($column->getType() === 'datetime') 
            ? $textColumn->dateTime()
            : $textColumn->date();
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return Tables\Columns\IconColumn::make($column->getName())
            ->sortable()
            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color(fn (bool $state): string => $state ? 'success' : 'danger');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return Tables\Columns\TextColumn::make($column->getName())
            ->wrap()
            ->placeholder(fn () => $this->placeholderHtml());
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if (Str::of($column->getName())->contains(['link', 'url'])) {
            return Tables\Columns\TextColumn::make($column->getName())
                ->url(fn ($record) => $record->{$column->getName()})
                ->color('primary')
                ->openUrlInNewTab();
        }

        $isPrimaryKey = $this->modelInstance->getKeyName() === $column->getName();

        $textColumn = Tables\Columns\TextColumn::make($column->getName())
            ->sortable($isPrimaryKey || $column->isNumeric())
            ->searchable($isPrimaryKey)
            ->copyable($isPrimaryKey)
            ->weight($isPrimaryKey ? FontWeight::Bold : null)
            ->fontFamily($isPrimaryKey ? FontFamily::Mono : null)
            ->icon($isPrimaryKey ? 'heroicon-m-clipboard-document' : null)
            ->iconPosition($isPrimaryKey ? IconPosition::After : null)
            ->placeholder(fn () => $this->placeholderHtml());

        if (! $isPrimaryKey && $column->isNumeric()) {
            return $textColumn->numeric($column->getDecimalPlaces());
        }

        return $textColumn;
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        return collect($this->getResourceColumns($exceptColumns, $overwriteColumns, $enumDictionary))->map(function (TableColumn $columnInstance) {
            return $columnInstance
                ->searchable(
                    isGlobal: $columnInstance->isGloballySearchable() ?: $this->isSearchableColumnByType($columnInstance, 'global'),
                    isIndividual: $columnInstance->isIndividuallySearchable() ?: $this->isSearchableColumnByType($columnInstance, 'individual'),
                )
                ->toggleable(
                    isToggledHiddenByDefault: ! empty($this->visibleColumns) && ! in_array($columnInstance->getName(), $this->visibleColumns)
                );
        })->all();
    }

    protected function isSearchableColumnByType(TableColumn $column, string $type): bool
    {
        if (! array_key_exists($column->getName(), $this->searchableColumns)) {
            return false;
        }

        $searchableType = $this->searchableColumns[$column->getName()];

        if ($searchableType === 'both') {
            return true;
        }

        return $searchableType === $type;
    }
}
