<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\Column as TableColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Support\DecimalFormatter;

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
        array $searchableColumns = [],
        array $excludedColumns = [],
    ): array {
        return (new static($modelClass))->excludedColumns($excludedColumns)->visibleColumns($visibleColumns)
            ->searchableColumns($searchableColumns)
            ->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary);
    }

    public static function makeForModel(
        Model $model,
        array $exceptColumns = [],
        array $overwriteColumns = [],
        array $enumDictionary = [],
        array $visibleColumns = [],
        array $searchableColumns = [],
        array $excludedColumns = [],
    ): array {
        return (new static($model))->excludedColumns($excludedColumns)->visibleColumns($visibleColumns)
            ->searchableColumns($searchableColumns)
            ->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary);
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        $name = "{$relationshipName}.{$relationshipTitleColumnName}";
        $textColumn = $this->isUserRelationship($relationshipName)
            ? UserColumn::make($name)->label(ucfirst(strtolower(Str::headline($relationshipName))))
            : TextColumn::make($name);

        $textColumn
            ->placeholder(fn () => $this->placeholderHtml())
            ->weight(FontWeight::Bold)
            ->color('primary')
            ->url(fn (?Model $record) => $this->relationshipUrl($record, $relationshipName));

        if (str_ends_with($column->getName(), '_type')) {
            $textColumn->state(fn (?Model $record) => RelationshipGuesser::getRecordTitle($record?->{$relationshipName}));
        }

        return $textColumn;
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return TextColumn::make($column->getName())
            ->badge()
            ->formatStateUsing(fn ($state) => $this->dictionaryLabel($state, $dictionary))
            ->color(fn ($state) => $this->dictionaryColor($state, $dictionary));
    }

    protected function handleArrayColumn(Column $column): ViewComponent
    {
        return TextColumn::make($column->getName())
            ->badge()
            ->placeholder(fn () => $this->placeholderHtml());
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $textColumn = TextColumn::make($column->getName())
            ->sortable()
            ->placeholder(fn () => $this->placeholderHtml());

        return ($column->getType() === 'datetime')
            ? $textColumn->dateTime()
            : $textColumn->date();
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return IconColumn::make($column->getName())
            ->sortable()
            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color(fn (bool $state): string => $state ? 'success' : 'danger');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return TextColumn::make($column->getName())
            ->wrap()
            ->placeholder(fn () => $this->placeholderHtml());
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if (Str::of($column->getName())->contains(['link', 'url'])) {
            return TextColumn::make($column->getName())
                ->url(fn ($record) => $record->{$column->getName()})
                ->color('primary')
                ->openUrlInNewTab();
        }

        $isPrimaryKey = $this->modelInstance->getKeyName() === $column->getName();

        $textColumn = TextColumn::make($column->getName())
            ->sortable($isPrimaryKey || $column->isNumeric())
            ->searchable($isPrimaryKey)
            ->copyable($isPrimaryKey)
            ->weight($isPrimaryKey ? FontWeight::Bold : null)
            ->fontFamily($isPrimaryKey ? FontFamily::Mono : null)
            ->icon($isPrimaryKey ? 'heroicon-m-clipboard-document' : null)
            ->iconPosition($isPrimaryKey ? IconPosition::After : null)
            ->placeholder(fn () => $this->placeholderHtml());

        if (! $isPrimaryKey && $column->isNumeric()) {
            return $textColumn->numeric()
                ->formatStateUsing(static fn (TextColumn $component, mixed $state): ?string => DecimalFormatter::format(
                    $state,
                    $column->getDecimalPlaces(),
                    $component->getTable()->getDefaultNumberLocale() ?? config('app.locale'),
                ));
        }

        return $textColumn;
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        $columns = $this->getResourceColumns($exceptColumns, $overwriteColumns, $enumDictionary);

        $names = array_values(array_map(static fn (TableColumn $column): string => $column->getName(), $columns));

        $this->ensureColumnsExist($names, $this->visibleColumns, 'Visible');
        $this->ensureColumnsExist($names, array_keys($this->searchableColumns), 'Searchable');

        if ($searchable = array_intersect($this->encryptedColumns, array_keys($this->searchableColumns))) {
            throw new InvalidArgumentException(sprintf(
                'Encrypted column(s) [%s] of [%s] cannot be searchable: the database only holds ciphertext.',
                implode(', ', $searchable),
                $this->modelInstance::class,
            ));
        }

        return collect($columns)->map(function (TableColumn $columnInstance) {
            $name = $columnInstance->getName();

            // Encrypted values are decrypted by the model, so SQL can neither search nor sort them.
            if (in_array($name, $this->encryptedColumns, true)) {
                return $columnInstance
                    ->searchable(false)
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: ! in_array($name, $this->visibleColumns));
            }

            return $columnInstance
                ->searchable(
                    isGlobal: $columnInstance->isGloballySearchable() ?: $this->isSearchableColumnByType($columnInstance, 'global'),
                    isIndividual: $columnInstance->isIndividuallySearchable() ?: $this->isSearchableColumnByType($columnInstance, 'individual'),
                )
                ->toggleable(
                    isToggledHiddenByDefault: ! empty($this->visibleColumns) && ! in_array($name, $this->visibleColumns)
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
