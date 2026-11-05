<?php

namespace Miguilim\FilamentAutoPanel;

use Filament\Actions\AssociateAction;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Miguilim\FilamentAutoPanel\Concerns\HasAutoPanelConfiguration;
use Miguilim\FilamentAutoPanel\Generators\FormGenerator;
use Miguilim\FilamentAutoPanel\Generators\InfolistGenerator;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Generators\TableGenerator;
use Miguilim\FilamentAutoPanel\Support\Actions;
use Miguilim\FilamentAutoPanel\Support\TableDefaults;

class AutoRelationManager extends RelationManager
{
    use HasAutoPanelConfiguration;

    protected static bool $associateAttachActions = true;

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
        return ['table' => [], 'form' => [], 'infolist' => []];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components(InfolistGenerator::makeForModel(
                ...$this->getSchemaOptions('infolist'),
                model: $this->getRelationship()->getModel(),
                exceptColumns: $this->getExceptRelationshipColumns(),
            ))
            ->columns(2);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(FormGenerator::makeForModel(
                ...$this->getSchemaOptions('form'),
                model: $this->getRelationship()->getModel(),
                exceptColumns: $this->getExceptRelationshipColumns(),
                relationManagerView: true,
            ))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        [$headerActions, $recordActions, $bulkActions] = $this->getBaseTableActions($table);
        $headerActions = Actions::merge(Actions::select($this->getActions(), 'showOnListPage'), $headerActions);

        if (static::getRelatedResource()) {
            $bulkActions = Actions::merge(Actions::select($this->getActions(), 'showOnBulkAction'), $bulkActions);

            return $table
                ->columns($this->withoutRelationshipColumns($table->getColumns()))
                ->pushHeaderActions($headerActions)
                ->recordActions(Actions::merge(Actions::select($this->getActions(), 'showOnTable'), $recordActions, $table->getRecordActions()))
                ->pushToolbarActions($bulkActions ? [BulkActionGroup::make($bulkActions)] : []);
        }

        $softDeletes = $this->hasSoftDeletes();
        $filters = $this->getFilters();

        if ($softDeletes) {
            $filters[] = TrashedFilter::make();
        }

        $bulkActions = Actions::merge($this->getBulkActions(), $bulkActions);

        $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $softDeletes ? $query->withoutGlobalScope(SoftDeletingScope::class) : $query)
            ->columns(TableGenerator::makeForModel(
                ...$this->getSchemaOptions('table'),
                model: $this->getRelationship()->getModel(),
                exceptColumns: $this->getExceptRelationshipColumns(),
            ))
            ->filters($filters)
            ->headerActions($headerActions)
            ->recordActions(Actions::merge($this->getTableActions(), $recordActions))
            ->toolbarActions($bulkActions ? [BulkActionGroup::make($bulkActions)] : []);

        return TableDefaults::sort($table, $this->getRelationship()->getModel());
    }

    public static function isIntrusive(): bool
    {
        $resource = static::getRelatedResource();

        return $resource && is_a($resource, AutoResource::class, true) ? $resource::isIntrusive() : static::$intrusive;
    }

    public function isReadOnly(): bool
    {
        $resource = static::getRelatedResource();

        return $resource && is_a($resource, AutoResource::class, true) ? $resource::isReadOnly() : static::$readOnly;
    }

    public function getBulkActions(): array
    {
        return Actions::merge(
            Actions::select($this->getActions(), 'showOnBulkAction'),
            Actions::bulk($this->isReadOnly(), $this->hasSoftDeletes()),
        );
    }

    public function getTableActions(): array
    {
        return Actions::merge(
            Actions::select($this->getActions(), 'showOnTable'),
            Actions::table($this->isReadOnly(), $this->hasSoftDeletes(), editable: true),
        );
    }

    /** @return array{array, array, array} */
    protected function getBaseTableActions(Table $table)
    {
        if ($this->isReadOnly()) {
            return [[], [], []];
        }

        $header = Actions::listing(false);
        $relationship = $this->getRelationship();

        if (! static::$associateAttachActions) {
            return [$header, [], []];
        }

        if ($relationship instanceof BelongsToMany) {
            return [[AttachAction::make(), ...$header], [DetachAction::make()], [DetachBulkAction::make()]];
        }

        $inverse = $table->getInverseRelationship();

        if (($relationship instanceof HasMany || $relationship instanceof MorphMany)
            && $inverse !== null
            && method_exists($relationship->getModel(), $inverse)
            && $this->hasNullableRelationshipColumns($relationship)) {
            return [[AssociateAction::make(), ...$header], [DissociateAction::make()], [DissociateBulkAction::make()]];
        }

        return [$header, [], []];
    }

    protected function hasNullableRelationshipColumns(HasOneOrMany $relationship): bool
    {
        $model = $relationship->getRelated();
        $nullableColumns = array_column(
            $model->getConnection()->getSchemaBuilder()->getColumns($model->getTable()),
            'nullable',
            'name',
        );
        $requiredColumns = [$relationship->getForeignKeyName()];

        if ($relationship instanceof MorphOneOrMany) {
            $requiredColumns[] = $relationship->getMorphType();
        }

        foreach ($requiredColumns as $name) {
            if (($nullableColumns[$name] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string> */
    protected function getExceptRelationshipColumns()
    {
        $relationship = $this->getRelationship();

        if ($relationship instanceof MorphOneOrMany) {
            return [$relationship->getForeignKeyName(), $relationship->getMorphType()];
        }

        return $relationship instanceof HasOneOrMany ? [$relationship->getForeignKeyName()] : [];
    }

    protected function getColumnsOverwriteMapped(string $type): array
    {
        return static::mapColumnOverrides($this->getColumnsOverwrite()[$type] ?? []);
    }

    protected function getSchemaOptions(string $type): array
    {
        $resource = static::getRelatedResource();

        if ($resource && is_a($resource, AutoResource::class, true)) {
            return $resource::getSchemaOptions($type);
        }

        $options = [
            'overwriteColumns' => $this->getColumnsOverwriteMapped($type),
            'enumDictionary' => static::$enumDictionary,
        ];

        if ($type === 'table') {
            $options['visibleColumns'] = static::$visibleColumns;
            $options['searchableColumns'] = static::$searchableColumns;
        }

        return $options;
    }

    protected function withoutRelationshipColumns(array $columns): array
    {
        $excluded = $this->getExceptRelationshipColumns();
        $relationships = [];
        $model = $this->getRelationship()->getModel();

        foreach ($excluded as $name) {
            $relationship = str_ends_with($name, '_type')
                ? RelationshipGuesser::guessMorphTo($name, $model)
                : RelationshipGuesser::guessBelongsTo($name, $model);

            if ($relationship) {
                $relationships[] = $relationship[0];
            }
        }

        return array_filter($columns, function ($column) use ($excluded, $relationships): bool {
            $name = $column->getName();

            return ! in_array($name, $excluded, true)
                && ! in_array(explode('.', $name, 2)[0], $relationships, true);
        });
    }

    protected function hasSoftDeletes(): bool
    {
        return method_exists($this->getRelationship()->getModel(), 'bootSoftDeletes');
    }
}
