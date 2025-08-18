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
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Filament\Actions\BulkActionGroup;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;

class AutoRelationManager extends RelationManager
{
    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $associateAttachActions = true;

    protected static bool $intrusive = true;

    protected static bool $readOnly = false;

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

    public function infolist(Schema $schema): Schema
    {
        $overwriteColumns = static::$relatedResource
            ? static::$relatedResource::getColumnsOverwriteMapped('infolist')
            : $this->getColumnsOverwriteMapped('infolist');

        return $schema
            ->components(InfolistGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: $this->getExceptRelationshipColumns(),
                overwriteColumns: $overwriteColumns,
                enumDictionary: static::$enumDictionary,
            ))
            ->columns(2);
    }

    public function form(Schema $schema): Schema
    {
        $overwriteColumns = static::$relatedResource
            ? static::$relatedResource::getColumnsOverwriteMapped('form')
            : $this->getColumnsOverwriteMapped('form');

        return $schema
            ->components(FormGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: $this->getExceptRelationshipColumns(),
                overwriteColumns: $overwriteColumns,
                enumDictionary: static::$enumDictionary,
                relationManagerView: true,
            ))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        [$baseHeaderActions, $baseActions, $baseBulkActions] = $this->getBaseTableActions($table);

        if (static::$relatedResource) {
            return $table
                ->pushHeaderActions($baseHeaderActions)
                ->recordActions([...$baseActions, ...$table->getRecordActions()])
                ->pushToolbarActions($baseBulkActions);
        }

        $hasSoftDeletes = method_exists($this->getRelationship()->getModel(), 'bootSoftDeletes');

        $defaultFilters       = [...$this->getFilters()];
        $defaultActions       = [...$this->getTableActions(), AutoEditAction::make(), ViewAction::make()];
        $defaultBulkActions   = [...$this->getBulkActions(), DeleteBulkAction::make()];

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
            ->headerActions($baseHeaderActions)
            ->recordActions([...$baseActions, ...$defaultActions])
            ->toolbarActions([BulkActionGroup::make($defaultBulkActions), ...$baseBulkActions]);
    }

    public static function isIntrusive(): bool
    {
        return static::$relatedResource ? static::$relatedResource::isIntrusive() : static::$intrusive;
    }

    public function isReadOnly(): bool
    {
        return static::$relatedResource ? static::$relatedResource::isReadOnly() : static::$readOnly;
    }

    public function getBulkActions(): array
    {
        return collect($this->getActions())
            ->filter(fn (AutoAction $action) => $action->showOnBulkAction)
            ->all();
    }

    public function getTableActions(): array
    {
        return collect($this->getActions())
            ->filter(fn (AutoAction $action) => $action->showOnTable)
            ->all();
    }

    protected function getBaseTableActions(Table $table)
    {
        $baseHeaderActions = [AutoCreateAction::make()];
        $baseActions       = [];
        $baseBulkActions   = [];

        // Associate action
        if (
            static::$associateAttachActions
            && method_exists($this->getRelationship()->getModel(), $table->getInverseRelationship())
            && ($this->getRelationship() instanceof HasMany || $this->getRelationship() instanceof MorphMany)
        ) {
            $baseHeaderActions = [AssociateAction::make(), ...$baseHeaderActions];
            $baseActions       = [DissociateAction::make(), ...$baseActions];
            $baseBulkActions   = [DissociateBulkAction::make(), ...$baseBulkActions];
        }

        // Attach action
        if (
            static::$associateAttachActions
            && ($this->getRelationship() instanceof BelongsToMany || $this->getRelationship() instanceof MorphToMany)
        ) {
            $baseHeaderActions = [AttachAction::make(), ...$baseHeaderActions];
            $baseActions       = [DetachAction::make(), ...$baseActions];
            $baseBulkActions   = [DetachBulkAction::make(), ...$baseBulkActions];
        }

        return [$baseHeaderActions, $baseActions, $baseBulkActions];
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
}
