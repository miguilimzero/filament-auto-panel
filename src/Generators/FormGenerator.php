<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\KeyValue;
use Filament\Support\Components\ViewComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;
use Miguilim\FilamentAutoPanel\Filament\StateCasts\JsonStateCast;

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
        return (new static($modelClass))->relationManagerView($relationManagerView)
            ->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary);
    }

    public static function makeForModel(Model $model, array $exceptColumns = [], array $overwriteColumns = [], array $enumDictionary = [], bool $relationManagerView = false): array
    {
        return (new static($model))->relationManagerView($relationManagerView)
            ->generateSchema($exceptColumns, $overwriteColumns, $enumDictionary);
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Select::make($column->getName())
            ->required($column->getNotNull())
            ->relationship($relationshipName, $relationshipTitleColumnName)
            ->forceSearchCaseInsensitive() // Compatibility with specific db collations
            ->searchable();
    }

    protected function handleEnumDictionaryColumn(Column $column, array $dictionary): ViewComponent
    {
        return Select::make($column->getName())
            ->required($column->getNotNull())
            ->options(
                collect($dictionary)->mapWithKeys(fn ($value, $key) => [$key => is_array($value) ? ($value[0] ?? $key) : $value])->all()
            );
    }

    protected function handleArrayColumn(Column $column): ViewComponent
    {
        if ($column->isObjectCast()) {
            return Textarea::make($column->getName())
                ->required($column->getNotNull())
                ->columnSpan('full')
                ->rules(['json'])
                ->stateCast(new JsonStateCast())
                ->dehydrateStateUsing(static fn (?string $state): mixed => filled($state)
                    ? json_decode($state, associative: false, flags: JSON_THROW_ON_ERROR)
                    : null);
        }

        return KeyValue::make($column->getName())
            ->required($column->getNotNull())
            ->columnSpanFull();
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        $dateColumn = ($column->getType() === 'datetime')
            ? DateTimePicker::make($column->getName())
            : DatePicker::make($column->getName());

        return $dateColumn
            ->required($column->getNotNull())
            ->disabled(in_array($column->getName(), $this->getTimestampColumnNames()));
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
                ->visibleOn(['create', 'edit']),
        ];
    }
}
