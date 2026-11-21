<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Filament\Schemas\Schema;

class FormData
{
    /**
     * Fill the schema, then drop every key that has no form field, so attributes
     * the form does not show never reach the Livewire snapshot.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $excludedColumns
     */
    public static function fill(Schema $schema, array $data, array $excludedColumns = []): void
    {
        $schema->fill(array_diff_key($data, array_flip($excludedColumns)));

        if (blank($schema->getStatePath())) {
            return;
        }

        $schema->rawState(array_intersect_key((array) $schema->getRawState(), static::fieldKeys($schema)));
    }

    /**
     * Hidden fields are kept: their visibility may depend on state that changes later.
     *
     * @return array<string, true>
     */
    protected static function fieldKeys(Schema $schema): array
    {
        $prefix = $schema->getStatePath() . '.';
        $keys = [];

        foreach ($schema->getFlatFields(withHidden: true) as $field) {
            $path = $field->getStatePath();

            if (str_starts_with($path, $prefix)) {
                $keys[explode('.', substr($path, strlen($prefix)), 2)[0]] = true;
            }
        }

        return $keys;
    }
}
