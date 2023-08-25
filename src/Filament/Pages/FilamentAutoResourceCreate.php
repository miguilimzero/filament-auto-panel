<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceCreate extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        return (static::getResource()::getIntrusive())
            ? $this->getModel()::forceCreate($data)
            : $this->getModel()::create($data);
    }
}
