<?php

namespace Miguilim\FilamentAutoResource\FilamentPages;

use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions;
use Illuminate\Database\Eloquent\Model;

class FilamentAutoResourceEdit extends EditRecord
{
    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($this::getResource()::$intrusive) {
            foreach ($data as $key => $value) {
                $record->{$key} = $value;
            }
            $record->save();

            return $record;
        }

        $record->update($data);

        return $record;
    }
}
