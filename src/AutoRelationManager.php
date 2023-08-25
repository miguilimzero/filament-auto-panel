<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoResource\Filament\Actions\CreateAction as CreateActionModified;
use Miguilim\FilamentAutoResource\Filament\Actions\EditAction as EditActionModified;
use Miguilim\FilamentAutoResource\Generators\FormGenerator;
use Miguilim\FilamentAutoResource\Generators\TableGenerator;

class AutoRelationManager extends RelationManager
{
    protected static string $relatedResource;

    protected static array $enumDictionary = [];

    protected static array $visibleColumns = [];

    protected static array $searchableColumns = [];

    protected static bool $intrusive = true;

    public function form(Form $form): Form
    {
        return $form
            ->schema(FormGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: [$this->getRelationship()->getForeignKeyName()],
                enumDictionary: static::$enumDictionary,
            ))
            ->columns(3);
    }

    public function tableExtra(Table $table): Table
    {
        return $table;
    }

    public function table(Table $table): Table
    {
        $finalTable     = $this->tableExtra($table);
        $hasSoftDeletes = method_exists($this->getRelationship()->getModel(), 'bootSoftDeletes');

        $defaultFilters       = [];
        $defaultHeaderActions = [CreateActionModified::make()];
        $defaultActions       = [Tables\Actions\ViewAction::make(), EditActionModified::make()];
        $defaultBulkActions   = [Tables\Actions\DeleteBulkAction::make()];

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

        return $finalTable
            ->modifyQueryUsing(fn(Builder $query): Builder => $query
                ->withoutGlobalScopes(array_filter([
                    $hasSoftDeletes ? SoftDeletingScope::class : null,
                ]))
            )
            ->columns(TableGenerator::make(
                modelClass: $this->getRelationship()->getModel()::class,
                exceptColumns: [$this->getRelationship()->getForeignKeyName()],
                enumDictionary: static::$enumDictionary,
                visibleColumns: static::$visibleColumns,
                searchableColumns: static::$searchableColumns,
            ))
            ->filters([...$finalTable->getFilters(), ...$defaultFilters])
            ->headerActions([...$finalTable->getHeaderActions(), ...$defaultHeaderActions])
            ->actions([...$finalTable->getActions(), ...$defaultActions])
            ->bulkActions([...$finalTable->getBulkActions(), ...$defaultBulkActions]);
    }

    public static function getIntrusive(): bool
    {
        return static::$intrusive;
    }
}
