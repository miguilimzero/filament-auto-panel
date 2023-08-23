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
            ->schema(FormGenerator::makeFormSchema(
                model: $this->getOwnerRecord()::class,
                enumDictionary: static::$enumDictionary,
                except: [$this->getRelationship()->getForeignKeyName()]
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
        $hasSoftDeletes = method_exists($this->getOwnerRecord(), 'bootSoftDeletes');

        $defaultFilters       = [];
        $defaultHeaderActions = [CreateActionModified::make()];
        $defaultActions       = [Tables\Actions\ViewAction::make(), EditActionModified::make()];
        $defaultBulkActions   = [Tables\Actions\DeleteBulkAction::make()];

        // Associate action
        if (
            method_exists($this->getOwnerRecord(), $table->getInverseRelationship())
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
            ->query(fn(Builder $builder): Builder => $builder
                ->withoutGlobalScopes(array_filter([
                    $hasSoftDeletes ? SoftDeletingScope::class : null,
                ]))
            )
            ->columns(TableGenerator::makeTableSchema(
                model: $this->getOwnerRecord()::class,
                visibleColumns: static::$visibleColumns,
                searchableColumns: static::$searchableColumns,
                enumDictionary: static::$enumDictionary,
                except: [$this->getRelationship()->getForeignKeyName()],
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
