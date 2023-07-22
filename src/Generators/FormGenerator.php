<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Doctrine\DBAL\Types;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FormGenerator 
{
    use Concerns\CanReadModelSchemas;

    public static array $generatedFormSchemas = [];

    protected Model $dummyModel;

    public function __construct(protected string $modelClass)
    {
        $this->dummyModel = new $modelClass();
    }

    public static function makeFormSchema(string $model, array $enumDictionary = [], array $except = []): array
    {
        $cacheKey = md5($model . json_encode($enumDictionary) . json_encode($except));
    
        return static::$generatedFormSchemas[$cacheKey] ??= (new self($model))->getResourceFormSchema($except, $enumDictionary);
    }

    protected function getResourceFormSchema(array $except, array $enumDictionary): array
    {
        $columns = $this->getResourceFormSchemaColumns($this->modelClass);
    
        $columnInstances = [];

        foreach ($columns as $key => $value) {
            if (in_array($key, $except)) {
                continue;
            }

            if (($this->dummyModel->getCasts()[$key] ?? '') === 'json') { // TODO: Add support for json cast columns
                continue;
            }

            $columnInstance = call_user_func([$value['type'], 'make'], $key);

            if (isset($enumDictionary[$key])) {
                $columnInstance = call_user_func([Forms\Components\Select::class, 'make'], $key);
                $columnInstance->options(
                    collect($enumDictionary[$key])->mapWithKeys(fn ($value, $key) => [$key => (is_array($value)) ? $value[0] : $value])->all()
                );

                unset($value['numeric']);
            }

            if ($this->dummyModel->getKeyName() === $key) {
                if (method_exists($this->dummyModel, 'initializeHasUuids')) {
                    $columnInstance->disabled();
                    $columnInstance->default($this->dummyModel->newUniqueId());
                } else if ($this->dummyModel->incrementing) {
                    $columnInstance->disabled();
                    $columnInstance->placeholder('Auto-incremented ID');
                    unset($value['required']);
                }
            }

            if (
                $columnInstance instanceof Forms\Components\Toggle
                || $columnInstance instanceof Forms\Components\Textarea
            ) {
                $columnInstance->columnSpan('full');
            }

            foreach ($value as $valueName => $parameters) {
                if($valueName === 'type') {
                    continue;
                }

                if($valueName === 'maxLength' && !($columnInstance instanceof Forms\Components\TextInput)) {
                    continue;
                }
                
                $columnInstance->{$valueName}(...$parameters);
            }

            $columnInstances[] = $columnInstance;
        }

        return [
            Forms\Components\Group::make()
                ->schema([
                    Forms\Components\Card::make()
                        ->schema($columnInstances)
                        ->columns(2),
                ])
                ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

            Forms\Components\Card::make()
                ->schema(array_filter([
                    Forms\Components\Placeholder::make('created_at')
                        ->label('Created at')
                        ->content(fn ($record): ?string => $record->created_at?->diffForHumans()),
                    (! $this->modelClass::isIgnoringTouch())
                        ? Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated at')
                            ->content(fn ($record): ?string => $record->updated_at?->diffForHumans())
                        : null,
                    (method_exists($this->modelClass, 'bootSoftDeletes')) 
                        ? Forms\Components\Placeholder::make('deleted_at')
                            ->label('Deleted at')
                            ->content(fn ($record): ?string => $record->deleted_at?->diffForHumans() ?? 'Never')
                        : null,
                ]))
                ->columnSpan(['lg' => 1])
                ->hidden(fn ($record) => $record === null),
        ];
    }

    protected function getResourceFormSchemaColumns(string $model): array
    {
        $table = $this->introspectTable($model);

        $components = [];

        foreach ($table->getColumns() as $column) {
            $columnName = $column->getName();

            if (Str::of($columnName)->is([
                'created_at',
                'deleted_at',
                'updated_at',
            ])) {
                continue;
            }

            $componentData = [];

            $componentData['type'] = $type = match ($column->getType()::class) {
                Types\BooleanType::class => Forms\Components\Toggle::class,
                Types\DateType::class => Forms\Components\DatePicker::class,
                Types\DateTimeType::class => Forms\Components\DateTimePicker::class,
                Types\TextType::class => Forms\Components\Textarea::class,
                default => Forms\Components\TextInput::class,
            };

            if (Str::of($columnName)->endsWith('_id')) {
                $guessedRelationshipName = $this->guessBelongsToRelationshipName($column, $model);

                if (filled($guessedRelationshipName)) {
                    $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName($column, app($model)->{$guessedRelationshipName}()->getModel()::class);

                    $componentData['type'] = $type = Forms\Components\Select::class;
                    $componentData['relationship'] = [$guessedRelationshipName, $guessedRelationshipTitleColumnName];
                    $componentData['searchable'] = [];
                    // $componentData['preload'] = [];
                }
            }

            if ($type === Forms\Components\TextInput::class) {
                if (Str::of($columnName)->contains(['email'])) {
                    $componentData['email'] = [];
                }

                if (Str::of($columnName)->contains(['password'])) {
                    $componentData['password'] = [];
                }

                if (Str::of($columnName)->contains(['phone', 'tel'])) {
                    $componentData['tel'] = [];
                }

                if (in_array(
                    $column->getType()::class,
                    [
                        Types\DecimalType::class,
                        Types\FloatType::class,
                        Types\BigIntType::class,
                        Types\IntegerType::class,
                        Types\SmallIntType::class,
                    ])) {
                    $componentData['numeric'] = [];
                }
            }

            if ($column->getNotnull()) {
                $componentData['required'] = [];
            }

            if (in_array($type, [Forms\Components\TextInput::class, Forms\Components\Textarea::class]) && ($length = $column->getLength())) {
                $componentData['maxLength'] = [$length];
            }

            $components[$columnName] = $componentData;
        }

        return $components;
    }
}