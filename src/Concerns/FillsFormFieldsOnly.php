<?php

namespace Miguilim\FilamentAutoPanel\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\AutoResource;
use Miguilim\FilamentAutoPanel\Support\FormData;

trait FillsFormFieldsOnly
{
    public function fillForm(array | Closure | null $data): static
    {
        $this->mountUsing(static function (Action $action, ?Schema $schema) use ($data): void {
            if ($schema === null) {
                return;
            }

            $data = $action->evaluate($data);

            if ($data === null) {
                $schema->fill();

                return;
            }

            FormData::fill($schema, $data, $action->getExcludedColumns());
        });

        return $this;
    }

    /** @return array<string> */
    public function getExcludedColumns(): array
    {
        $livewire = $this->getLivewire();

        if ($livewire instanceof AutoRelationManager) {
            return $livewire->getExcludedColumns();
        }

        $resource = $livewire && method_exists($livewire, 'getResource') ? $livewire::getResource() : null;

        return $resource && is_a($resource, AutoResource::class, true) ? $resource::getExcludedColumns() : [];
    }
}
