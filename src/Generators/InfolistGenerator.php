<?php

namespace Miguilim\FilamentAutoPanel\Generators;
use Filament\Infolists;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Str;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

class InfolistGenerator extends AbstractGenerator
{
    public static function make(string $modelClass, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = []): array
    {
        return static::getCachedSchema(
            parameters: func_get_args(),
            function: fn () => (new static($modelClass))->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary)
        );
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Infolists\Components\TextEntry::make("{$relationshipName}.{$relationshipTitleColumnName}")
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
        return Infolists\Components\TextEntry::make($column->getName())
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
        return Infolists\Components\TextEntry::make($column->getName())
            ->badge()
            ->placeholder(fn () => $this->placeholderHtml())
            ->columnSpan('full');
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $textEntry = Infolists\Components\TextEntry::make($column->getName())
            ->placeholder(fn () => $this->placeholderHtml());

        return ($column->getType() === 'datetime')
            ? $textEntry->dateTime()
            : $textEntry->date();
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return Infolists\Components\IconEntry::make($column->getName())
            ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
            ->columnSpan('full');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return Infolists\Components\TextEntry::make($column->getName())
            ->placeholder(fn () => $this->placeholderHtml())
            ->columnSpan('full');
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if (Str::of($column->getName())->contains(['link', 'url'])) {
            return Infolists\Components\TextEntry::make($column->getName())
                ->url(fn ($record) => $record->{$column->getName()})
                ->color('primary')
                ->openUrlInNewTab();
        }

        $isPrimaryKey = $this->modelInstance->getKeyName() === $column->getName();

        $textEntry = Infolists\Components\TextEntry::make($column->getName())
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
        $columnInstances = $this->getResourceColumns([...$exceptColumns, ...['created_at', 'updated_at', 'deleted_at']], $overwriteColumns, $enumDictionary);

        $hasCreatedAt   = $this->modelInstance->usesTimestamps() && $this->modelInstance->getCreatedAtColumn() !== null;
        $hasUpdatedAt   = $this->modelInstance->usesTimestamps() && $this->modelInstance->getUpdatedAtColumn() !== null;
        $hasSoftDeletes = method_exists($this->modelInstance, 'getDeletedAtColumn') && $this->modelInstance->getDeletedAtColumn() !== null;

        $timestampsSection = ($hasCreatedAt || $hasUpdatedAt || $hasSoftDeletes)
            ? Infolists\Components\Section::make()
                ->schema(array_filter([
                    $hasCreatedAt ? Infolists\Components\TextEntry::make($this->modelInstance->getCreatedAtColumn())->since() : null,
                    $hasUpdatedAt ? Infolists\Components\TextEntry::make($this->modelInstance->getUpdatedAtColumn())->since() : null,
                    $hasSoftDeletes ? Infolists\Components\TextEntry::make($this->modelInstance->getDeletedAtColumn())->since()->placeholder(fn () => $this->placeholderHtml('Never')) : null,
                ]))
                ->columnSpan(['lg' => 1])
                ->hidden(fn ($record) => $record === null)
            : null;

        return array_filter([
            Infolists\Components\Group::make()
                ->schema([
                    Infolists\Components\Section::make()
                        ->schema($columnInstances)
                        ->columns(2),
                ])
                ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

            $timestampsSection,
        ]);
    }
}
