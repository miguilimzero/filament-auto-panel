<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TagsInput;
use Filament\Support\Components\ViewComponent;
use Illuminate\Support\Str;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

class FormGenerator extends AbstractGenerator
{
    protected bool $relationManagerView;

    public function relationManagerView(bool $relationManagerView)
    {
        $this->relationManagerView = $relationManagerView;

        return $this;
    }

    public static function make(string $modelClass, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = [], bool $relationManagerView = false): array
    {
        return static::getCachedSchema(
            parameters: func_get_args(),
            function: fn() => (new static($modelClass))->relationManagerView($relationManagerView)
                ->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary)
        );
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Select::make($column->getName())
            ->required($column->getNotNull())
            ->relationship($relationshipName, $relationshipTitleColumnName)
            // ->preload()
            ->searchable();
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return Select::make($column->getName())
            ->required($column->getNotNull())
            ->options(
                collect($dictionary)->mapWithKeys(fn ($value, $key) => [$key => (is_array($value)) ? $value[0] : $value])->all()
            );
    }

    protected function handleArrayColumn(Column $column): ViewComponent
    {
        return TagsInput::make($column->getName())
            ->required($column->getNotNull());
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $dateColumn = ($column->getType() === 'datetime')
            ? DateTimePicker::make($column->getName())
            : DatePicker::make($column->getName());

        return $dateColumn
            ->required($column->getNotNull())
            ->disabled(in_array($column->getName(), ['created_at', 'updated_at', 'deleted_at']));
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return Toggle::make($column->getName())
            ->columnSpan('full');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return Textarea::make($column->getName())
            ->required($column->getNotNull())
            ->columnSpan('full')
            ->maxLength($column->getLength());
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        if ($this->modelInstance->getKeyName() === $column->getName()) {
            if (method_exists($this->modelInstance, 'initializeHasUuids')) {
                return TextInput::make($column->getName())
                    ->disabled()
                    ->default($this->modelInstance->newUniqueId());
            }
            if ($this->modelInstance->incrementing) {
                return TextInput::make($column->getName())
                    ->disabled()
                    ->placeholder('Auto-generated ID');
            }
        }

        $textInput = TextInput::make($column->getName())
            ->required($column->getNotNull())
            ->email(Str::contains($column->getName(), 'email'))
            ->tel(Str::contains($column->getName(), ['phone', 'tel']));

        if ($column->isNumeric()) {
            return $textInput->numeric(); // TextInput numeric method does not have precision parameter, and it cannot have a maxLength()
        }

        return $textInput->maxLength($column->getLength());
    }

    protected function generateSchema(array $exceptColumns, array $overwriteColumns, array $enumDictionary): array
    {
        $formsSchema = $this->getResourceColumns($exceptColumns, $overwriteColumns, $enumDictionary);

        if ($this->relationManagerView) {
            return $formsSchema;
        }

        return [
            Group::make()
                ->schema($formsSchema)
                ->columns(2)
                ->columnSpanFull()
                ->visibleOn('create'),

            Group::make()
                ->schema($formsSchema)
                ->columns(2)
                ->columnSpanFull()
                ->visibleOn('edit'),
        ];
    }
}
