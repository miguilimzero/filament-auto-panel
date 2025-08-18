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
        $defaultHeaderActions = [AutoCreateAction::make()];
        $defaultActions       = [...$this->getTableActions(), ...$this->makeViewAndEditActions()];
        $defaultBulkActions   = [...$this->getBulkActions(), DeleteBulkAction::make()];

        // Associate action
        if (
            static::$associateAttachActions
            && method_exists($this->getRelationship()->getModel(), $table->getInverseRelationship())
            && ($this->getRelationship() instanceof HasMany || $this->getRelationship() instanceof MorphMany)
        ) {
            $defaultHeaderActions = [AssociateAction::make(), ...$defaultHeaderActions];
            $defaultActions       = [DissociateAction::make(), ...$defaultActions];
            $defaultBulkActions   = [DissociateBulkAction::make(), ...$defaultBulkActions];
        }

        // Attach action
        if (
            static::$associateAttachActions
            && ($this->getRelationship() instanceof BelongsToMany || $this->getRelationship() instanceof MorphToMany)
        ) {
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
            ->toolbarActions([BulkActionGroup::make($defaultBulkActions)]);
    }

    public static function isIntrusive(): bool
    {
        return static::$intrusive;
    }

    public function isReadOnly(): bool
    {
        return static::$readOnly;
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

    protected function makeViewAndEditActions(): array
    {
        // if ($relatedResource = TableGenerator::tryToGuessRelatedResource($this->getRelationship()->getModel())) {
        //     return [
        //         ViewAction::make()->url(fn ($record) => $relatedResource::getUrl('view', ['record' => $record])),
        //     ];
        // }

        return [
            ViewAction::make(),

            AutoEditAction::make(),
        ];
    }
}
