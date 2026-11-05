<?php

namespace Miguilim\FilamentAutoPanel\Generators\Concerns;

use BackedEnum;
use Filament\Models\Contracts\HasName;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\HtmlString;

trait HasGeneratorHelpers
{
    protected function introspectTable(): Builder
    {
        return $this->modelInstance
            ->getConnection()
            ->getSchemaBuilder();
    }

    protected function placeholderHtml(string $placeholder = 'null'): HtmlString
    {
        return new HtmlString("<span class=\"text-gray-500 dark:text-gray-400\">{$placeholder}</span>");
    }

    protected function isUserRelationship(string $relationshipName, string $titleColumn): bool
    {
        $relationship = $this->modelInstance->{$relationshipName}();

        if (! $relationship instanceof BelongsTo || $relationship instanceof MorphTo) {
            return false;
        }

        $related = $relationship->getRelated();

        return $related instanceof Authenticatable
            && ($titleColumn === 'name' || $related instanceof HasName || $related->hasAttribute('name'));
    }

    protected function relationshipUrl(?Model $record, string $relationship): ?string
    {
        $related = $record?->{$relationship};

        if ($related === null) {
            return null;
        }

        $resource = static::tryToGuessRelatedResource($related);

        return $resource && $resource::hasPage('view')
            ? $resource::getUrl('view', ['record' => $related])
            : null;
    }

    protected function dictionaryLabel(mixed $state, array $dictionary): mixed
    {
        $state = $state instanceof BackedEnum ? $state->value : $state;
        $entry = $dictionary[$state] ?? $state;

        return is_array($entry) ? ($entry[0] ?? $state) : $entry;
    }

    protected function dictionaryColor(mixed $state, array $dictionary): string|array
    {
        $state = $state instanceof BackedEnum ? $state->value : $state;
        $entry = $dictionary[$state] ?? null;

        return is_array($entry) ? ($entry[1] ?? 'primary') : 'primary';
    }

    protected function getTimestampColumnNames(): array
    {
        return array_values(array_unique(array_filter([
            $this->modelInstance->usesTimestamps() ? $this->modelInstance->getCreatedAtColumn() : null,
            $this->modelInstance->usesTimestamps() ? $this->modelInstance->getUpdatedAtColumn() : null,
            method_exists($this->modelInstance, 'getDeletedAtColumn') ? $this->modelInstance->getDeletedAtColumn() : null,
        ])));
    }
}