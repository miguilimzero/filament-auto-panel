<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages\Concerns;

trait OverwriteFillForm
{
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        if (static::getResource()::getIntrusive()) {
            $data = $this->getRecord()->setHidden([])->attributesToArray();
        } else {
            $data = $this->getRecord()->attributesToArray();
        }

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);

        $this->callHook('afterFill');
    }
}