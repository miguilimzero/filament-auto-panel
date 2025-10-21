<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Illuminate\Database\Eloquent\Model;

class RelationshipGuesser
{
    use CanReadModelSchemas;

    protected static $instance;

    /**
     * @return string[]
     */
    public static function guessBelongsTo(string $column, Model $model): array
    {
        $guessedRelationshipName = static::getInstance()->guessBelongsToRelationshipName($column, $model::class);

        if (filled($guessedRelationshipName)) {
            return [
                $guessedRelationshipName,
                static::guessTitleColumnName($column, $model, $guessedRelationshipName)
            ];
        }

        return [];
    }

    public static function guessTitleColumnName(string $column, Model $model, string $relationshipName): string
    {
        return static::getInstance()->guessBelongsToRelationshipTitleColumnName(
            column: $column,
            model: $model->{$relationshipName}()->getModel()::class
        );
    }

    protected static function getInstance(): static
    {
        return static::$instance = new static();
    }
}
