<?php

namespace Miguilim\FilamentAutoResource\Filament\Actions;

use Filament\Tables\Actions\CreateAction as CreateActionBase;

use Filament\Forms\ComponentContainer;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class CreateAction extends CreateActionBase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-support::actions/create.single.label', ['label' => $this->getModelLabel()]));

        $this->modalHeading(fn (): string => __('filament-support::actions/create.single.modal.heading', ['label' => $this->getModelLabel()]));

        $this->modalButton(__('filament-support::actions/create.single.modal.actions.create.label'));

        $this->extraModalActions(function (): array {
            return $this->isCreateAnotherDisabled() ? [] : [
                $this->makeExtraModalAction('createAnother', ['another' => true])
                    ->label(__('filament-support::actions/create.single.modal.actions.create_another.label')),
            ];
        });

        $this->successNotificationTitle(__('filament-support::actions/create.single.messages.created'));

        $this->button();

        $this->action(function (array $arguments, ComponentContainer $form, HasTable $livewire): void {
            $model = $this->getModel();

            $record = $this->process(function (array $data) use ($model): Model {
                $relationship = $this->getRelationship();

                if (! $relationship) {
                    return $model::create($data);
                }

                $createMethod = ($this->getLivewire()->getIntrusive()) 
                    ? 'forceCreate'
                    : 'create';

                if ($relationship instanceof BelongsToMany) {
                    $pivotColumns = $relationship->getPivotColumns();

                    return $relationship->{$createMethod}(
                        Arr::except($data, $pivotColumns),
                        Arr::only($data, $pivotColumns),
                    );
                }

                return $relationship->{$createMethod}($data);
            });

            $this->record($record);
            $form->model($record)->saveRelationships();

            $livewire->mountedTableActionRecord($record->getKey());

            if ($arguments['another'] ?? false) {
                $this->callAfter();
                $this->sendSuccessNotification();

                $this->record(null);

                // Ensure that the form record is anonymized so that relationships aren't loaded.
                $form->model($model);
                $livewire->mountedTableActionRecord(null);

                $form->fill();

                $this->halt();

                return;
            }

            $this->success();
        });
    }
}