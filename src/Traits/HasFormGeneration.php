<?php

namespace Miguilim\FilamentAutoResource\Traits;

use Doctrine\DBAL\Types;
use Filament\Forms;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Illuminate\Support\Str;

trait HasFormGeneration
{
    public static function makeFormSchema(string $model): array
    {
        return (new self())->getResourceFormSchema($model);
    }

    protected function getResourceFormSchema(string $model): array
    {
        $columns = $this->getResourceFormSchemaColumns($model);

        $columnInstances = [];

        foreach ($columns as $key => $value) {
            $columnInstance = call_user_func([$value['type'], 'make'], $key);

            foreach ($value as $valueName => $parameters) {
                if($valueName === 'type') {
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
                    Forms\Components\Placeholder::make('updated_at')
                        ->label('Updated at')
                        ->content(fn ($record): ?string => $record->updated_at?->diffForHumans()),
                    (method_exists($model, 'bootSoftDeletes')) 
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
            if ($column->getAutoincrement()) {
                continue;
            }

            $columnName = $column->getName();

            if (Str::of($columnName)->is([
                'created_at',
                'deleted_at',
                'updated_at',
                '*_token',
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
