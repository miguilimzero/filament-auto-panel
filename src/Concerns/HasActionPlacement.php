<?php

namespace Miguilim\FilamentAutoPanel\Concerns;

trait HasActionPlacement
{
    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = false;

    public bool $showOnListPage = false;

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

    public function showOnListPage(bool $condition = true): static
    {
        $this->showOnListPage = $condition;

        return $this;
    }
}
