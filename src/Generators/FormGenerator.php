<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\KeyValue;
use Filament\Support\Components\ViewComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    protected function getResourceColumns(array $exceptColumn, array $overwriteColumns, array $enumDictionary): array
    {
        $columns = parent::getResourceColumns($exceptColumn, $overwriteColumns, $enumDictionary);
        $morphs = [];
        $morphKeys = [];

        foreach ($columns as $typeColumn => $field) {
            if (! str_ends_with($typeColumn, '_type')) {
                continue;
            }

            $keyColumn = substr($typeColumn, 0, -5) . '_id';

            if (! isset($columns[$keyColumn])
                || isset($overwriteColumns[$typeColumn]) || isset($overwriteColumns[$keyColumn])
                || isset($enumDictionary[$typeColumn]) || isset($enumDictionary[$keyColumn])
                || ! $field instanceof Field || ! $columns[$keyColumn] instanceof Field) {
                continue;
            }

            $name = RelationshipGuesser::guessMorphToRelationshipName($typeColumn, $this->modelInstance);

            if ($name === null || isset($columns[$name])) {
                continue;
            }

            $component = $overwriteColumns[$name] ?? null;

            if ($component === null) {
                $numericKey = $columns[$keyColumn] instanceof TextInput && $columns[$keyColumn]->isNumeric();
                $types = $this->getMorphToTypes($name, $typeColumn, $numericKey);

                if (! $types) {
                    continue;
                }

                $component = MorphToSelect::make($name)
                    ->types($types)
                    ->required($field->isRequired() || $columns[$keyColumn]->isRequired())
                    ->searchable()
                    ->columns(2)
                    ->columnSpanFull();
            }

            $morphs[$typeColumn] = [$name, $component];
            $morphKeys[] = $keyColumn;
        }

        $result = [];

        foreach ($columns as $name => $component) {
            if (in_array($name, $morphKeys, true)) {
                continue;
            }

            if (isset($morphs[$name])) {
                [$name, $component] = $morphs[$name];
            }

            $result[$name] = $component;
        }

        return $result;
    }

    /** @return array<Type> */
    protected function getMorphToTypes(string $relationshipName, string $typeColumn, bool $numericKey): array
    {
        $source = clone $this->modelInstance;
        $source->setAttribute($typeColumn, null);
        $ownerKey = $source->{$relationshipName}()->getOwnerKeyName();
        $types = [];

        foreach (Filament::getCurrentPanel()?->getResources() ?? [] as $resource) {
            if (! $resource::canAccess()) {
                continue;
            }

            $modelClass = $resource::getModel();

            if (isset($types[$modelClass])
                || (Relation::requiresMorphMap() && ! in_array($modelClass, Relation::morphMap(), true))) {
                continue;
            }

            $related = new $modelClass();

            if (($numericKey && $related->getKeyType() !== 'int')
                || ($ownerKey !== null && $ownerKey !== $related->getKeyName())) {
                continue;
            }

            if ($related->getConnectionName() === null) {
                $related->setConnection($this->modelInstance->getConnectionName());
            }

            $title = RelationshipGuesser::guessModelTitleColumnName($related);
            $types[$modelClass] = Type::make($modelClass)
                ->label($resource::getModelLabel())
                ->titleAttribute($title)
                ->searchColumns(RelationshipGuesser::guessModelSearchColumns($related, $title))
                ->forceSearchCaseInsensitive()
                // Native selected-label lookups already contain the key constraint.
                ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $related->newQuery()
                    ->mergeConstraintsFrom($query)
                    ->select($related->qualifyColumn('*'))
                    ->orderBy($related->qualifyColumn($title)))
                ->getOptionLabelFromRecordUsing(static fn (Model $record): string => (string) (filled($value = $record->getAttribute($title)) ? $value : $record->getKey()));
        }

        return array_values($types);
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Select::make($column->getName())
            ->label(ucfirst(strtolower(Str::headline($relationshipName))))
            ->required($column->getNotNull())
            ->relationship($relationshipName, $relationshipTitleColumnName)
            ->forceSearchCaseInsensitive() // Compatibility with specific db collations
            ->searchable(RelationshipGuesser::guessSearchColumns($this->modelInstance, $relationshipName, $relationshipTitleColumnName));
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
