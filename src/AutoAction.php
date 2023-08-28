<?php

namespace Miguilim\FilamentAutoPanel;

use Closure;
use Filament\Actions\Action as PageAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class AutoAction
{
    protected Closure | string | null $action = null;

    protected array $actionMethodsAndArguments = [];

    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = false;

    public function __construct(protected ?string $name = null)
    {

    }

    public static function make(?string $name = null): static
    {
        return new static($name);
    }

    public function __call(string $method, array $arguments): static
    {
        $this->actionMethodsAndArguments[$method] = $arguments;

        return $this;
    }

    public function showOnBulkAction(bool $condition = true)
    {
        $this->showOnBulkAction = $condition;

        return $this;
    }

    public function showOnTable(bool $condition = true)
    {
        $this->showOnTable = $condition;

        return $this;
    }

    public function showOnViewPage(bool $condition = true)
    {
        $this->showOnViewPage = $condition;

        return $this;
    }

    public function action(Closure | string | null $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function convertToBulkAction(): BulkAction
    {
        $actionClosure = $this->action;
        $action        = BulkAction::make($this->name)
            ->action($actionClosure);

        foreach ($this->actionMethodsAndArguments as $method => $arguments) {
            $action->{$method}(...$arguments);
        }

        return $action;
    }

    public function convertToTableAction(): Action
    {
        $actionClosure = $this->action;
        $action        = Action::make($this->name)
            ->action(fn($record, $data) => $actionClosure(new Collection([$record]), $data));

        foreach ($this->actionMethodsAndArguments as $method => $arguments) {
            $action->{$method}(...$arguments);
        }

        return $action;
    }

    public function convertToViewPageAction(): PageAction
    {
        $actionClosure = $this->action;
        $action        = PageAction::make($this->name)
            ->action(fn($record, $data) => $actionClosure(new Collection([$record]), $data));
        
        foreach ($this->actionMethodsAndArguments as $method => $arguments) {
            $action->{$method}(...$arguments);
        }

        return $action;
    }
}