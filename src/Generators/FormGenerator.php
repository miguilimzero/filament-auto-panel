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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\KeyValue;
use Filament\Support\Components\ViewComponent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;
use Miguilim\FilamentAutoPanel\Filament\StateCasts\JsonStateCast;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Support\RelationshipOptionLabel;

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
                    ->allowHtml()
                    ->native(false)
                    ->columns(2)
                    ->columnSpanFull();

                $component->modifyKeySelectUsing(static fn (Select $select): Select => $select->getOptionLabelUsing(
                    static function (Select $component, Get $get, mixed $value) use ($types, $typeColumn): string {
                        $alias = $get($typeColumn);

                        if (! is_string($alias) && ! is_int($alias)) {
                            return '';
                        }

                        foreach ($types as $type) {
                            if ($type->getAlias() === (string) $alias) {
                                return $component->evaluate($type->getOptionLabelUsing, ['value' => $value]) ?? '';
                            }
                        }

                        return '';
                    },
                ));
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
            $userColumn = $related instanceof Authenticatable ? UserColumn::make("{$relationshipName}.{$title}") : null;
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
                ->getOptionLabelFromRecordUsing(static fn (Model $record): string => RelationshipOptionLabel::format($record, $title, $userColumn));
        }

        return array_values($types);
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        $relationship = $this->modelInstance->{$relationshipName}();
        $related = $relationship->getRelated();
        $hasJoins = filled($relationship->getQuery()->toBase()->joins);
        $userColumn = $related instanceof Authenticatable ? UserColumn::make("{$relationshipName}.{$relationshipTitleColumnName}") : null;

        return Select::make($column->getName())
            ->label(ucfirst(strtolower(Str::headline($relationshipName))))
            ->required($column->getNotNull())
            ->relationship($relationshipName, $relationshipTitleColumnName, modifyQueryUsing: static function (Builder $query) use ($hasJoins, $relationshipTitleColumnName): Builder {
                if ($hasJoins) {
                    $query->select($query->getModel()->qualifyColumn('*'));
                }

                if (empty($query->getQuery()->orders)) {
                    $query->orderBy($query->qualifyColumn($relationshipTitleColumnName));
                }

                return $query;
            })
            ->getOptionLabelFromRecordUsing(static fn (Model $record, Select $component): string => RelationshipOptionLabel::format($record, $component->getRelationshipTitleAttribute(), $userColumn))
            ->getOptionLabelUsing(static fn (Select $component): string => ($record = $component->getSelectedRecord()) === null ? '' : $component->getOptionLabelFromRecord($record))
            ->allowHtml()
            ->native(false)
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
