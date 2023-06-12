<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class AutoRelationManager extends RelationManager
{
    protected static string $relatedResource;

    protected static array $visibleColumns = [];

    protected static array $enumDictionary = [];

    protected static bool $intrusive = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(FilamentAutoResourceHelper::makeFormSchema(
                model: static::getRelationshipModelStatically(), 
                enumDictionary: static::$enumDictionary,
                except: [static::getRelationshipStatically()->getForeignKeyName()]
            ))
            ->columns(3);
    }
    
    public static function tableExtra(Table $table): Table
    {
        return $table;
    }

    public static function table(Table $table): Table
    {
        $finalTable = static::tableExtra($table);
        $hasSoftDeletes = method_exists(static::getRelationshipModelStatically(), 'bootSoftDeletes');

        $defaultFilters = [];
        $defaultHeaderActions = [Tables\Actions\CreateAction::make()];
        $defaultActions = [Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()];
        $defaultBulkActions = [Tables\Actions\DeleteBulkAction::make()];

        $relationshipInstance = static::getRelationshipStatically();


        // Associate action
        if(
            method_exists(self::getRelationshipModelStatically(), static::getInverseRelationshipNameStatically()) 
            && ($relationshipInstance instanceof HasMany || $relationshipInstance instanceof MorphMany)
        ) {
            $defaultHeaderActions = [Tables\Actions\AssociateAction::make(), ...$defaultHeaderActions];
            $defaultActions = [Tables\Actions\DissociateAction::make(), ...$defaultActions];
            $defaultBulkActions = [Tables\Actions\DissociateBulkAction::make(), ...$defaultBulkActions];
        }

        // Attach action
        if($relationshipInstance instanceof BelongsToMany || $relationshipInstance instanceof MorphToMany) {
            $defaultHeaderActions = [Tables\Actions\AttachAction::make(), ...$defaultHeaderActions];
            $defaultActions = [Tables\Actions\DetachAction::make(), ...$defaultActions];
            $defaultBulkActions = [Tables\Actions\DetachBulkAction::make(), ...$defaultBulkActions];
        }

        // Soft deletes
        if ($hasSoftDeletes) {
            $defaultFilters[] = Tables\Filters\TrashedFilter::make();

            $defaultActions[] = Tables\Actions\RestoreAction::make();

            $defaultBulkActions[] = Tables\Actions\RestoreBulkAction::make();
            $defaultBulkActions[] = Tables\Actions\ForceDeleteBulkAction::make();
        }

        return $finalTable
            ->columns(FilamentAutoResourceHelper::makeTableSchema(
                model: static::getRelationshipModelStatically(), 
                visibleColumns: static::$visibleColumns,
                enumDictionary: static::$enumDictionary,
                except: [static::getRelationshipStatically()->getForeignKeyName()],
            ))
            ->filters([...$finalTable->getFilters(), ...$defaultFilters])
            ->headerActions([...$finalTable->getHeaderActions(), ...$defaultHeaderActions])
            ->actions([...$finalTable->getActions(), ...$defaultActions])
            ->bulkActions([...$finalTable->getBulkActions(), ...$defaultBulkActions]);
    }

    protected function getTableQuery(): Builder
    {
        $parent = parent::getTableQuery();

        if (method_exists(static::getRelationshipModelStatically(), 'bootSoftDeletes')) {
            $parent->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        return $parent;
    }

    protected static function getRelationshipStatically(): Relation
    {
        $dummy = new (static::$relatedResource::getModel());

        return $dummy->{static::$relationship}();
    }

    protected static function getRelationshipModelStatically(): string
    {
        return static::getRelationshipStatically()->getRelated()::class;
    }

    protected static function getInverseRelationshipNameStatically(): string
    {
        return static::$inverseRelationship ?? (string) Str::of(class_basename(static::$relatedResource::getModel()))
            ->plural()
            ->camel();
    }
}
