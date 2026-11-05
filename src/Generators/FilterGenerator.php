<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use BackedEnum;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\Operators\IsFilledOperator;
use Filament\QueryBuilder\Constraints\RelationshipConstraint;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Miguilim\FilamentAutoPanel\Filament\Filters\IntegerComparisonOperator;
use Miguilim\FilamentAutoPanel\Generators\Objects\Column;

class FilterGenerator
{
    protected const STRUCTURED_CASTS = [
        'Illuminate\\Database\\Eloquent\\Casts\\AsArrayObject',
        'Illuminate\\Database\\Eloquent\\Casts\\AsCollection',
        'Illuminate\\Database\\Eloquent\\Casts\\AsEnumArrayObject',
        'Illuminate\\Database\\Eloquent\\Casts\\AsEnumCollection',
        'Illuminate\\Database\\Eloquent\\Casts\\AsEncryptedArrayObject',
        'Illuminate\\Database\\Eloquent\\Casts\\AsEncryptedCollection',
        'Illuminate\\Database\\Eloquent\\Casts\\AsFluent',
        'Illuminate\\Database\\Eloquent\\Casts\\AsVector',
    ];

    public function __construct(protected Model $model)
    {
    }

    /** @return array<QueryBuilder> */
    public static function make(string $modelClass, array $exceptColumns = [], array $enumDictionary = []): array
    {
        return static::makeForModel(new $modelClass(), $exceptColumns, $enumDictionary);
    }

    /** @return array<QueryBuilder> */
    public static function makeForModel(Model $model, array $exceptColumns = [], array $enumDictionary = []): array
    {
        $constraints = (new static($model))->generateConstraints($exceptColumns, $enumDictionary);

        return $constraints ? [QueryBuilder::make()->constraints($constraints)] : [];
    }

    /** @return array<QueryBuilder> */
    public static function makeForTable(Table $table, Model $model, array $exceptColumns = [], array $enumDictionary = []): array
    {
        $relationship = $table->getRelationship();

        if ($relationship !== null) {
            $model = $relationship instanceof Relation ? $relationship->getRelated() : $relationship->getModel();

            if ($relationship instanceof HasOneOrMany) {
                $exceptColumns[] = $relationship->getForeignKeyName();
            }

            if ($relationship instanceof MorphOneOrMany) {
                $exceptColumns[] = $relationship->getMorphType();
            }
        }

        return static::makeForModel($model, $exceptColumns, $enumDictionary);
    }

    /** @return array<Constraint> */
    protected function generateConstraints(array $exceptColumns, array $enumDictionary): array
    {
        $constraints = [];
        $connection = $this->model->getConnection();
        $casts = $this->model->getCasts();

        foreach ($connection->getSchemaBuilder()->getColumns($this->model->getTable()) as $metadata) {
            $name = $metadata['name'];

            if (in_array($name, $exceptColumns, true) || ! $this->isFilterable($this->model, $name)) {
                continue;
            }

            $cast = $casts[$name] ?? null;
            $physicalColumn = new Column($metadata, driver: $connection->getDriverName());
            $column = new Column($metadata, $cast, $connection->getDriverName());

            if ($column->getType() === 'json' || $physicalColumn->getType() === 'json') {
                continue;
            }

            if ($cast === 'timestamp' && in_array($physicalColumn->getType(), ['date', 'datetime'], true)) {
                $column = $physicalColumn;
            }

            if (isset($enumDictionary[$name])) {
                $options = [];

                foreach ($enumDictionary[$name] as $value => $label) {
                    $options[$value] = is_array($label) ? ($label[0] ?? $value) : $label;
                }

                $constraint = SelectConstraint::make($name)->options($options)->multiple()->searchable();
            } elseif ($cast !== null && enum_exists($cast) && is_a($cast, BackedEnum::class, true)) {
                $constraint = SelectConstraint::make($name)->options($cast)->multiple()->searchable();
            } elseif ($constraint = $this->makeRelationshipConstraint($column)) {
                $constraints[] = $constraint;

                continue;
            } else {
                $constraint = match ($column->getType()) {
                    'boolean' => BooleanConstraint::make($name),
                    'integer', 'decimal' => $this->makeNumberConstraint($column),
                    'date', 'datetime' => DateConstraint::make($name)->time($column->getType() === 'datetime'),
                    'string', 'textarea', 'uuid', 'ulid', 'enum', 'citext' => TextConstraint::make($name),
                    default => null,
                };
            }

            if ($constraint !== null) {
                $constraints[] = $constraint->nullable(! $column->getNotNull());
            }
        }

        return $constraints;
    }

    protected function isFilterable(Model $model, string $name): bool
    {
        $cast = explode(':', $model->getCasts()[$name] ?? '', 2)[0];

        return ! in_array($name, $model->getHidden(), true)
            && (! $model->getVisible() || in_array($name, $model->getVisible(), true))
            && ! in_array($cast, ['encrypted', 'hashed', ...static::STRUCTURED_CASTS], true);
    }

    protected function makeNumberConstraint(Column $column): NumberConstraint
    {
        $constraint = NumberConstraint::make($column->getName())->integer($column->isInteger());

        if ($column->isInteger()) {
            $constraint->operators([
                IntegerComparisonOperator::make('isMin'),
                IntegerComparisonOperator::make('isMax'),
                IntegerComparisonOperator::make('equals'),
                IsFilledOperator::make()->visible(fn (): bool => $constraint->isNullable()),
            ]);
        }

        return $constraint;
    }

    protected function makeRelationshipConstraint(Column $column): ?RelationshipConstraint
    {
        $name = RelationshipGuesser::guessBelongsToRelationshipName($column->getName(), $this->model);

        if ($name === null) {
            return null;
        }

        $actualRelationship = Relation::noConstraints(fn () => $this->model->{$name}());
        $related = $actualRelationship->getRelated();
        $modelClass = $this->model::class;
        $nativeModel = new $modelClass();

        // Native relationship constraints resolve a model class, not the runtime instance.
        if ($nativeModel->getTable() !== $this->model->getTable()
            || $nativeModel->getConnection()->getName() !== $this->model->getConnection()->getName()
            || $related->getConnection()->getName() !== $this->model->getConnection()->getName()
            || $actualRelationship->getOwnerKeyName() !== $related->getKeyName()) {
            return null;
        }

        $nativeRelationship = Relation::noConstraints(fn () => $nativeModel->{$name}());

        if (! $nativeRelationship instanceof BelongsTo) {
            return null;
        }

        $nativeRelated = $nativeRelationship->getRelated();

        if ($nativeRelationship->getForeignKeyName() !== $column->getName()
            || $nativeRelationship->getOwnerKeyName() !== $related->getKeyName()
            || $nativeRelated::class !== $related::class
            || $nativeRelated->getTable() !== $related->getTable()
            || $nativeRelated->getConnection()->getName() !== $related->getConnection()->getName()) {
            return null;
        }

        $actualQuery = $actualRelationship->getQuery()->toBase();
        $nativeQuery = $nativeRelationship->getQuery()->toBase();

        if ($actualQuery->toSql() !== $nativeQuery->toSql() || $actualQuery->getBindings() !== $nativeQuery->getBindings()) {
            return null;
        }

        $title = RelationshipGuesser::guessModelTitleColumnName($related);

        if (! $this->isFilterable($related, $title)) {
            return null;
        }

        $metadata = collect($related->getConnection()->getSchemaBuilder()->getColumns($related->getTable()))->firstWhere('name', $title);

        if ($metadata === null) {
            return null;
        }

        $titleColumn = new Column($metadata, $related->getCasts()[$title] ?? null, $related->getConnection()->getDriverName());

        if (! in_array($titleColumn->getType(), ['string', 'textarea', 'integer', 'decimal', 'uuid', 'ulid', 'enum', 'citext'], true)) {
            return null;
        }

        return RelationshipConstraint::make($name)
            ->emptyable(! $column->getNotNull())
            ->selectable(IsRelatedToOperator::make()->titleAttribute($title)->searchable()->multiple()->forceSearchCaseInsensitive());
    }
}
