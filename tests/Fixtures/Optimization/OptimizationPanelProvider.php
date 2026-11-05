<?php

namespace Miguilim\FilamentAutoPanel\Tests\Fixtures\Optimization;

use Filament\Panel;
use Filament\PanelProvider;

class OptimizationPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('optimization')
            ->default()
            ->path('admin')
            ->resources([OwnerResource::class]);
    }
}
