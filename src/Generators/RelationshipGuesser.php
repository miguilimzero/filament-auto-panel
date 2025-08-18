<?php

namespace Miguilim\FilamentAutoPanel\Generators;

use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Illuminate\Database\Eloquent\Model;

class RelationshipGuesser
{
    use CanReadModelSchemas;

    public static function try(string $column, Model $model): array
    {
        $instance = new static();

        $guessedRelationshipName = $instance->guessBelongsToRelationshipName($column, $model::class);

        if (filled($guessedRelationshipName)) {
            $guessedRelationshipTitleColumnName = $instance->guessBelongsToRelationshipTitleColumnName(
                column: $column,
                model: $model->{$guessedRelationshipName}()->getModel()::class
            );

            return [$guessedRelationshipName, $guessedRelationshipTitleColumnName];
        }

        return [];
    }
}
