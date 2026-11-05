<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Filament\Actions\CreateAction;
use Miguilim\FilamentAutoPanel\Concerns\HasActionPlacement;
use Miguilim\FilamentAutoPanel\Concerns\InteractsWithIntrusiveModels;

class AutoCreateAction extends CreateAction
{
    use HasActionPlacement;
    use InteractsWithIntrusiveModels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showOnListPage();
    }
}
