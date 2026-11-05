<?php

namespace Miguilim\FilamentAutoPanel\Generators;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

class InfolistGenerator extends AbstractGenerator
{
    public static function make(string $modelClass, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = []): array
    {
        return (new static($modelClass))->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary);
    }

    public static function makeForModel(Model $model, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = []): array
    {
        return (new static($model))->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary);
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        $textEntry = TextEntry::make("{$relationshipName}.{$relationshipTitleColumnName}")
            ->label(ucfirst(strtolower(Str::headline($relationshipName))))
            ->placeholder(fn () => $this->placeholderHtml())
            ->weight(FontWeight::Bold)
            ->color('primary')
            ->url(fn (?Model $record) => $this->relationshipUrl($record, $relationshipName));

        if (str_ends_with($column->getName(), '_type')) {
            $textEntry->state(fn (?Model $record) => RelationshipGuesser::getRecordTitle($record?->{$relationshipName}));
        }

        return $textEntry;
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return TextEntry::make($column->getName())
            ->badge()
            ->formatStateUsing(fn ($state) => $this->dictionaryLabel($state, $dictionary))
            ->color(fn ($state) => $this->dictionaryColor($state, $dictionary));
    }

    protected function handleArrayColumn(Column $column): ViewComponent
    {
        return KeyValueEntry::make($column->getName())
            ->state(function (?Model $record) use ($column): mixed {
                $state = $record?->getAttribute($column->getName());

                return is_string($state)
                    ? (array) json_decode($state, associative: true, flags: JSON_THROW_ON_ERROR)
                    : $state;
            })
            ->placeholder(fn () => $this->placeholderHtml())
            ->columnSpanFull();
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $textEntry = TextEntry::make($column->getName())
            ->placeholder(fn () => $this->placeholderHtml());

        return ($column->getType() === 'datetime')
            ? $textEntry->dateTime()
            : $textEntry->date();
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return IconEntry::make($column->getName())
            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
            ->columnSpan('full');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return TextEntry::make($column->getName())
            ->placeholder(fn () => $this->placeholderHtml())
            ->columnSpan('full');
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if (Str::of($column->getName())->contains(['link', 'url'])) {
            return TextEntry::make($column->getName())
                ->url(fn ($record) => $record->{$column->getName()})
                ->color('primary')
                ->openUrlInNewTab();
        }

        $isPrimaryKey = $this->modelInstance->getKeyName() === $column->getName();

        $textEntry = TextEntry::make($column->getName())
            ->copyable($isPrimaryKey)
            ->weight($isPrimaryKey ? FontWeight::Bold : null)
            ->fontFamily($isPrimaryKey ? FontFamily::Mono : null)
            ->icon($isPrimaryKey ? 'heroicon-s-clipboard-document' : null)
            ->iconPosition($isPrimaryKey ? IconPosition::After : null)
            ->placeholder(fn () => $this->placeholderHtml());

        if (! $isPrimaryKey && $column->isNumeric()) {
            return $textEntry->numeric($column->getDecimalPlaces());
        }

        return $textEntry;
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        $columnInstances = $this->getResourceColumns($exceptColumns, $overwriteColumns, $enumDictionary);
        $timestampEntries = [];
        $deletedAt = method_exists($this->modelInstance, 'getDeletedAtColumn') ? $this->modelInstance->getDeletedAtColumn() : null;

        foreach ($this->getTimestampColumnNames() as $name) {
            if (! isset($columnInstances[$name])) {
                continue;
            }

            $entry = $columnInstances[$name];
            unset($columnInstances[$name]);

            if (! isset($overwriteColumns[$name]) && ! isset($enumDictionary[$name])) {
                $entry = TextEntry::make($name)->since();

                if ($name === $deletedAt) {
                    $entry->placeholder(fn () => $this->placeholderHtml('Never'));
                }
            }

            $timestampEntries[] = $entry;
        }

        $timestampsSection = $timestampEntries
            ? Section::make()
                ->schema($timestampEntries)
                ->columnSpan(['lg' => 1])
                ->hidden(fn ($record) => $record === null)
            : null;

        return array_filter([
            Group::make()
                ->schema([
                    Section::make()
                        ->schema($columnInstances)
                        ->columns(2),
                ])
                ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

            $timestampsSection,
        ]);
    }
}
