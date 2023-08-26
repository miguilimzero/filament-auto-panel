<?php

namespace Miguilim\FilamentAuto\Generators;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types;
use Filament\Forms;
use Filament\Support\Components\ViewComponent;
use Illuminate\Support\Str;

class FormGenerator extends AbstractGenerator
{
    public static function make(string $modelClass, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = []): array
    {
        return static::getCachedSchema(fn() => (new static($modelClass))->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary));
    }
    
    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Forms\Components\Select::make($column->getName())
            ->required($column->getNotNull())
            ->relationship($relationshipName, $relationshipTitleColumnName)
            // ->preload()
            ->searchable();
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return Forms\Components\Select::make($column->getName())
            ->required($column->getNotNull())
            ->options(
                collect($dictionary)->mapWithKeys(fn ($value, $key) => [$key => (is_array($value)) ? $value[0] : $value])->all()
            );
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $isDisabled = in_array($column->getName(), ['created_at', 'updated_at', 'deleted_at']);

        if ($column->getType() instanceof Types\DateTimeType) {
            return Forms\Components\DateTimePicker::make($column->getName())
                ->required($column->getNotNull())
                ->disabled($isDisabled);
        }

        return Forms\Components\DatePicker::make($column->getName())
            ->required($column->getNotNull())
            ->disabled($isDisabled);
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return Forms\Components\Toggle::make($column->getName())
            ->columnSpan('full');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return Forms\Components\Textarea::make($column->getName())
            ->required($column->getNotNull())
            ->columnSpan('full')
            ->maxLength($column->getLength());
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if ($this->modelInstance->getKeyName() === $column->getName()) {
            if (method_exists($this->modelInstance, 'initializeHasUuids')) {
                return Forms\Components\TextInput::make($column->getName())
                    ->disabled()
                    ->default($this->modelInstance->newUniqueId());
            }
            if ($this->modelInstance->incrementing) {
                return Forms\Components\TextInput::make($column->getName())
                    ->disabled()
                    ->placeholder('Auto-generated ID');
            }
        }

        $textInput = Forms\Components\TextInput::make($column->getName())
            ->required($column->getNotNull())
            ->email(Str::contains($column->getName(), 'email'))
            ->tel(Str::contains($column->getName(), ['phone', 'tel']))
            ->numeric($this->isNumericColumn($column));

        if (! $this->isNumericColumn($column)) {
            $textInput->maxLength($column->getLength());
        }

        return $textInput;
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        return $this->getResourceColumns($exceptColumns, $overwriteColumns, $enumDictionary);
    }
}