<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Str;

class InfolistGenerator extends AbstractGenerator
{
    public static function make(string $modelClass, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = []): array
    {
        return static::getCachedSchema(fn() => (new static($modelClass))->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary));
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Infolists\Components\TextEntry::make("{$relationshipName}.{$relationshipTitleColumnName}")
            ->weight(FontWeight::Bold)
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
        return Infolists\Components\TextEntry::make($column->getName())
            ->formatStateUsing(function($state) use($dictionary) {
                $finalFormat = $dictionary[$state] ?? $state;

                return (is_array($finalFormat)) ? $finalFormat[0] : $finalFormat;
            })->color(function($state) use($dictionary) {
                if (! is_array($dictionary[$state]) || ! array_key_exists(1, $dictionary[$state])) {
                    return 'primary';
                }

                return $dictionary[$state][1];
            });
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        return Infolists\Components\TextEntry::make($column->getName())
            ->date($column->getType() instanceof Types\DateType)
            ->dateTime($column->getType() instanceof Types\DateTimeType);
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
            ->placeholder('Null')
            ->columnSpan('full');
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        $isPrimaryKey = $this->modelInstance->getKeyName() === $column->getName();

        return Infolists\Components\TextEntry::make($column->getName())
            ->copyable($isPrimaryKey)
            ->weight($isPrimaryKey ? FontWeight::Bold : null)
            ->fontFamily($isPrimaryKey ? FontFamily::Mono : null)
            ->placeholder('Null');
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        $columnInstances = $this->getResourceColumns([...$exceptColumns, ...['created_at', 'updated_at', 'deleted_at']], $overwriteColumns, $enumDictionary);

        return [
            Infolists\Components\Group::make()
                ->schema([
                    Infolists\Components\Section::make()
                        ->schema($columnInstances)
                        ->columns(2),
                ])
                ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

                Infolists\Components\Section::make()
                ->schema(array_filter([
                    Infolists\Components\TextEntry::make('created_at')->since(),
                    (! $this->modelInstance::isIgnoringTouch()) ? Infolists\Components\TextEntry::make('updated_at')->since() : null,
                    (method_exists($this->modelInstance, 'bootSoftDeletes')) ? Infolists\Components\TextEntry::make('deleted_at')->since()->placeholder('Never') : null,
                ]))
                ->columnSpan(['lg' => 1])
                ->hidden(fn ($record) => $record === null),
        ];
    }
}