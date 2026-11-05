<?php

namespace Miguilim\FilamentAutoPanel\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\AutoRelationManager;
use Miguilim\FilamentAutoPanel\AutoResource;

trait InteractsWithIntrusiveModels
{
    public function process(?Closure $default, array $parameters = []): mixed
    {
        // Retain Filament's lifecycle and always restore mass-assignment protection.
        return $this->isIntrusive()
            ? Model::unguarded(fn () => parent::process($default, $parameters))
            : parent::process($default, $parameters);
    }

    protected function isIntrusive(): bool
    {
        $livewire = $this->getLivewire();

        if ($livewire instanceof AutoRelationManager) {
            return $livewire::isIntrusive();
        }

        $resource = $livewire && method_exists($livewire, 'getResource') ? $livewire::getResource() : null;

        return $resource && is_a($resource, AutoResource::class, true) && $resource::isIntrusive();
    }
}
