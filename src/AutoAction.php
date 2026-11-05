<?php

namespace Miguilim\FilamentAutoPanel;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Miguilim\FilamentAutoPanel\Concerns\HasActionPlacement;

class AutoAction extends Action
{
    use HasActionPlacement;

    public function getExtraAttributes(): array
    {
        if ($this->canAccessSelectedRecords()) {
            return [
                'x-cloak' => true,
                'x-show' => 'getSelectedRecordsCount()',
                ...parent::getExtraAttributes(),
            ];
        }

        return parent::getExtraAttributes();
    }

    public function getSelectedRecords(): EloquentCollection | Collection | LazyCollection
    {
        if (($record = $this->getRecord()) !== null) {
            return new EloquentCollection([$record]);
        }

        $records = $this->getLivewire()->getSelectedTableRecords($this->shouldFetchSelectedRecords(), $this->getSelectedRecordsChunkSize());

        $this->totalSelectedRecordsCount = ($records instanceof LazyCollection)
            ? $this->getLivewire()->getSelectedTableRecordsQuery(shouldFetchSelectedRecords: false)->count()
            : $records->count();
        $this->successfulSelectedRecordsCount = $this->totalSelectedRecordsCount;

        return $records;
    }
}
