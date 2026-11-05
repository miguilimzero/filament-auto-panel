<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Concerns\HasActionPlacement;
use Miguilim\FilamentAutoPanel\Concerns\InteractsWithIntrusiveModels;

class AutoEditAction extends EditAction
{
    use HasActionPlacement;
    use InteractsWithIntrusiveModels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showOnViewPage();

        $this->mutateRecordDataUsing(fn (array $data, Model $record): array => $this->isIntrusive()
            ? [...(clone $record)->setHidden([])->attributesToArray(), ...$data]
            : $data);
    }
}
