<?php

namespace Miguilim\FilamentAutoPanel\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceCreate extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        // TODO: Add support for native tenancy?

        return (static::getResource()::getIntrusive())
            ? $this->getModel()::forceCreate($data)
            : $this->getModel()::create($data);
    }
}
