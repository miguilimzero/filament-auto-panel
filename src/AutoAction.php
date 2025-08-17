<?php

namespace Miguilim\FilamentAutoPanel;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class AutoAction extends Action
{
    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = false;

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
        if ($this->getRecord() !== null) { // Adaptation to work on table and on view page (also removed f (! $this->canAccessSelectedRecords()) validation)
            return collect([$this->getRecord()]);
        }

        $records = $this->getLivewire()->getSelectedTableRecords($this->shouldFetchSelectedRecords(), $this->getSelectedRecordsChunkSize());

        $this->totalSelectedRecordsCount = ($records instanceof LazyCollection)
            ? $this->getLivewire()->getSelectedTableRecordsQuery(shouldFetchSelectedRecords: false)->count()
            : $records->count();
        $this->successfulSelectedRecordsCount = $this->totalSelectedRecordsCount;

        return $records;
    }

    public function showOnBulkAction(bool $condition = true): static
    {
        $this->showOnBulkAction = $condition;

        return $this;
    }

    public function showOnTable(bool $condition = true): static
    {
        $this->showOnTable = $condition;

        return $this;
    }

    public function showOnViewPage(bool $condition = true): static
    {
        $this->showOnViewPage = $condition;

        return $this;
    }
}
