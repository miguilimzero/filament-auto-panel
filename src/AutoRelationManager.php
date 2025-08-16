<?php

namespace Miguilim\FilamentAutoPanel;

use Filament\Schemas\Schema;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;

class AutoRelationManager extends RelationManager
{
    protected static ?string $relatedResource;

    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $intrusive = true;

    protected static bool $readyOnly = false;

    public function getFilters(): array
    {
        return [];
    }

    public function getActions(): array
    {
        return [];
    }

    public function getColumnsOverwrite(): array
    {
        return [
            'table' => [],
            'form' => [],
            'infolist' => [],
        ];
    }

    public function isReadOnly(): bool
    {
        return static::$readyOnly;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components(InfolistGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: $this->getExceptRelationshipColumns(),
                overwriteColumns: $this->getColumnsOverwriteMapped('infolist'),
                enumDictionary: static::$enumDictionary,
            ))
            ->columns(2);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(FormGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: $this->getExceptRelationshipColumns(),
                overwriteColumns: $this->getColumnsOverwriteMapped('form'),
                enumDictionary: static::$enumDictionary,
                relationManagerView: true,
            ))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        $hasSoftDeletes = method_exists($this->getRelationship()->getModel(), 'bootSoftDeletes');

        $defaultFilters       = [...$this->getFilters()];
        $defaultHeaderActions = [$this->makeCreateAction()];
        $defaultActions       = [...$this->getTableActions(), ...$this->makeViewAndEditActions()];
        $defaultBulkActions   = [...$this->getBulkActions(), DeleteBulkAction::make()];

        // Associate action
        if (
            method_exists($this->getRelationship()->getModel(), $table->getInverseRelationship())
            && ($this->getRelationship() instanceof HasMany || $this->getRelationship() instanceof MorphMany)
        ) {
            $defaultHeaderActions = [AssociateAction::make(), ...$defaultHeaderActions];
            $defaultActions       = [DissociateAction::make(), ...$defaultActions];
            $defaultBulkActions   = [DissociateBulkAction::make(), ...$defaultBulkActions];
        }

        // Attach action
        if ($this->getRelationship() instanceof BelongsToMany || $this->getRelationship() instanceof MorphToMany) {
            $defaultHeaderActions = [AttachAction::make(), ...$defaultHeaderActions];
            $defaultActions       = [DetachAction::make(), ...$defaultActions];
            $defaultBulkActions   = [DetachBulkAction::make(), ...$defaultBulkActions];
        }

        // Soft deletes
        if ($hasSoftDeletes) {
            $defaultFilters[] = TrashedFilter::make();

            $defaultActions[] = RestoreAction::make();

            $defaultBulkActions[] = RestoreBulkAction::make();
            $defaultBulkActions[] = ForceDeleteBulkAction::make();
        }

        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query
                ->withoutGlobalScopes(array_filter([
                    $hasSoftDeletes ? SoftDeletingScope::class : null,
                ]))
            )
            ->columns(TableGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: $this->getExceptRelationshipColumns(),
                overwriteColumns: $this->getColumnsOverwriteMapped('table'),
                enumDictionary: static::$enumDictionary,
                visibleColumns: static::$visibleColumns,
                searchableColumns: static::$searchableColumns,
            ))
            ->filters($defaultFilters)
            ->headerActions($defaultHeaderActions)
            ->recordActions($defaultActions)
            ->toolbarActions($defaultBulkActions);
    }

    public static function getIntrusive(): bool
    {
        return static::$intrusive;
    }

    public function getBulkActions(): array
    {
        return collect($this->getActions())
            ->filter(fn (AutoAction $action) => $action->showOnBulkAction)
            ->map(fn (AutoAction $action) => $action->convertToBulkAction())
            ->all();
    }

    public function getTableActions(): array
    {
        return collect($this->getActions())
            ->filter(fn (AutoAction $action) => $action->showOnTable)
            ->map(fn (AutoAction $action) => $action->convertToTableAction())
            ->all();
    }

    protected function getExceptRelationshipColumns()
    {
        $relationship = $this->getRelationship();

        if ($relationship instanceof HasOne || $relationship instanceof HasMany) {
            return [$relationship->getForeignKeyName()];
        }

        return [];
    }

    protected function getColumnsOverwriteMapped(string $type): array
    {
        return collect($this->getColumnsOverwrite()[$type])
            ->mapWithKeys(fn ($column) => [$column->getName() => $column])
            ->all();
    }

    protected function makeCreateAction()
    {
        return CreateAction::make(); // TODO: Add support for intrusive mode
    }

    protected function makeViewAndEditActions(): array
    {
        if ($relatedResource = TableGenerator::tryToGuessRelatedResource($this->getRelationship()->getModel())) {
            return [
                ViewAction::make()->url(fn ($record) => $relatedResource::getUrl('view', ['record' => $record])),
            ];
        }

        return [
            ViewAction::make(),

            EditAction::make()
                ->fillForm(function (Model $record): array {
                    if (static::getIntrusive()) {
                        return $record->setHidden([])->attributesToArray();
                    } else {
                        return $record->attributesToArray();
                    }
                })->using(function (array $data, Model $record, Table $table) {
                    $relationship = $table->getRelationship();

                    if ($relationship instanceof BelongsToMany) {
                        $pivotColumns = $relationship->getPivotColumns();
                        $pivotData = Arr::only($data, $pivotColumns);

                        if (count($pivotColumns)) {
                            if (static::getIntrusive()) {
                                $record->{$relationship->getPivotAccessor()}->forceFill($pivotData)->save();
                            } else {
                                $record->{$relationship->getPivotAccessor()}->update($pivotData);
                            }
                        }

                        $data = Arr::except($data, $pivotColumns);
                    }

                    if (static::getIntrusive()) {
                        $record->forceFill($data)->save();
                    } else {
                        $record->update($data);
                    }
                })
        ];
    }
}
