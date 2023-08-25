<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types;
use Filament\Forms;
use Filament\Support\Components\ViewComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FormGenerator extends AbstractGenerator
{
    public static array $generatedFormSchemas = [];

    public static function makeSchema(string $model, array $enumDictionary = [], array $except = []): array
    {
        $cacheKey = md5($model . json_encode($enumDictionary) . json_encode($except));
    
        return static::$generatedFormSchemas[$cacheKey] ??= (new self($model))->getResourceFormSchema($except, $enumDictionary);
    }

    protected function handleRelationshipColumn(Column $column, string $relationshipName, string $relationshipTitleColumnName): ViewComponent
    {
        return Forms\Components\Select::make($column->getName())
            ->relationship($relationshipName, $relationshipTitleColumnName)
            ->searchable();
            // ->preload();
    }

    protected function handleDateColumn(Column $column): ViewComponent
    {
        if ($column->getType() instanceof Types\DateTimeType) {
            return Forms\Components\DateTimePicker::make($column->getName());
        }

        return Forms\Components\DatePicker::make($column->getName())
            ->required($column->getNotNull());
    }

    protected function handleBooleanColumn(Column $column): ViewComponent
    {
        return Forms\Components\Toggle::make($column->getName())
            ->columnSpan('full');
    }

    protected function handleTextColumn(Column $column): ViewComponent
    {
        return Forms\Components\Textarea::make($column->getName())
            ->columnSpan('full')
            ->maxLength($column->getLength());
    }

    protected function handleDefaultColumn(Column $column): ViewComponent
    {
        return Forms\Components\TextInput::make($column->getName())
            ->maxLength($column->getLength())
            ->email(Str::contains($column->getName(), 'email'))
            ->tel(Str::contains($column->getName(), ['phone', 'tel']))
            ->numeric($this->isNumericColumn($column));
    }

    protected function getResourceFormSchema(array $exceptColumns, array $enumDictionary): array
    {
        $columnInstances = $this->getResourceColumns([...$exceptColumns, ...['created_at', 'updated_at', 'deleted_at']]);

        foreach ($columns as $key => $value) {
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
}