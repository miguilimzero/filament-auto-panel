<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceCreate extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model
    {
        if ($this::getResource()::$intrusive) {
            $model = new ($this->getModel());
        
            foreach ($data as $key => $value) {
                $model->{$key} = $value;
            }
        
            $model->save();
        
            return $model;
        }

        return $this->getModel()::create($data);
    }
}
