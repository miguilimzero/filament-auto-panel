<?php

namespace Miguilim\FilamentAuto;

use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAuto\Generators\FormGenerator;
use Miguilim\FilamentAuto\Generators\InfolistGenerator;
use Miguilim\FilamentAuto\Generators\TableGenerator;

class AutoRelationManager extends RelationManager
{
    protected static string $relatedResource;

    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $intrusive = true;

    public function getFilters(): array
    {
        return [];
    }

    public function getActions(): array
    {
        return [];
    }

    public static function getTableColumnsOverwrite(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(InfolistGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: [$this->getRelationship()->getForeignKeyName()], 
                enumDictionary: static::$enumDictionary
            ))
            ->columns(2);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(FormGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: [$this->getRelationship()->getForeignKeyName()],
                enumDictionary: static::$enumDictionary,
            ))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        $hasSoftDeletes = method_exists($this->getRelationship()->getModel(), 'bootSoftDeletes');

        // TODO: Implement Create & Edit actions
        $defaultFilters       = [...$this->getFilters()];
        $defaultHeaderActions = [];
        $defaultActions       = [...$this->getTableActions(), Tables\Actions\ViewAction::make()];
        $defaultBulkActions   = [...$this->getBulkActions(), Tables\Actions\DeleteBulkAction::make()];

        // Associate action
        if (
            method_exists($this->getRelationship()->getModel(), $table->getInverseRelationship())
            && ($this->getRelationship() instanceof HasMany || $this->getRelationship() instanceof MorphMany)
        ) {
            $defaultHeaderActions = [Tables\Actions\AssociateAction::make(), ...$defaultHeaderActions];
            $defaultActions       = [Tables\Actions\DissociateAction::make(), ...$defaultActions];
            $defaultBulkActions   = [Tables\Actions\DissociateBulkAction::make(), ...$defaultBulkActions];
        }

        // Attach action
        if ($this->getRelationship() instanceof BelongsToMany || $this->getRelationship() instanceof MorphToMany) {
            $defaultHeaderActions = [Tables\Actions\AttachAction::make(), ...$defaultHeaderActions];
            $defaultActions       = [Tables\Actions\DetachAction::make(), ...$defaultActions];
            $defaultBulkActions   = [Tables\Actions\DetachBulkAction::make(), ...$defaultBulkActions];
        }

        // Soft deletes
        if ($hasSoftDeletes) {
            $defaultFilters[] = Tables\Filters\TrashedFilter::make();

            $defaultActions[] = Tables\Actions\RestoreAction::make();

            $defaultBulkActions[] = Tables\Actions\RestoreBulkAction::make();
            $defaultBulkActions[] = Tables\Actions\ForceDeleteBulkAction::make();
        }

        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query
                ->withoutGlobalScopes(array_filter([
                    $hasSoftDeletes ? SoftDeletingScope::class : null,
                ]))
            )
            ->columns(TableGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: [$this->getRelationship()->getForeignKeyName()],
                overwriteColumns: static::getTableColumnsOverwrite(),
                enumDictionary: static::$enumDictionary,
                visibleColumns: static::$visibleColumns,
                searchableColumns: static::$searchableColumns,
            ))
            ->filters($defaultFilters)
            ->headerActions($defaultHeaderActions)
            ->actions($defaultActions)
            ->bulkActions($defaultBulkActions);
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
}
