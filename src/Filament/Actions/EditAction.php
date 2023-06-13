<?php

namespace Miguilim\FilamentAutoResource\Filament\Actions;

use Filament\Tables\Actions\EditAction as EditActionBase;

use Filament\Forms\ComponentContainer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

class EditAction extends EditActionBase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-support::actions/edit.single.label'));

        $this->modalHeading(fn (): string => __('filament-support::actions/edit.single.modal.heading', ['label' => $this->getRecordTitle()]));

        $this->modalButton(__('filament-support::actions/edit.single.modal.actions.save.label'));

        $this->successNotificationTitle(__('filament-support::actions/edit.single.messages.saved'));

        $this->icon('heroicon-s-pencil');

        $this->mountUsing(function (ComponentContainer $form, Model $record): void {    
            $data = $record->attributesToArray();

            if ($this->mutateRecordDataUsing) {
                $data = $this->evaluate($this->mutateRecordDataUsing, ['data' => $data]);
            }

            $form->fill($data);
        });

        $this->action(function (): void {
            $this->process(function (array $data, Model $record) {
                $relationship = $this->getRelationship();

                $isIntrusive = ($this->getLivewire()->getIntrusive()) ;

                if ($relationship instanceof BelongsToMany) {
                    $pivotColumns = $relationship->getPivotColumns();
                    $pivotData = Arr::only($data, $pivotColumns);

                    if (count($pivotColumns)) {
                        if ($isIntrusive) {
                            foreach ($pivotData as $key => $value) {
                                $record->{$key} = $value;
                            }
                            $record->save();
                        } else {
                            $record->{$relationship->getPivotAccessor()}->update($pivotData);
                        }
                    }

                    $data = Arr::except($data, $pivotColumns);
                }

                if ($isIntrusive) {
                    foreach ($data as $key => $value) {
                        $record->{$key} = $value;
                    }
                    $record->save();
                } else {
                    $record->update($data);
                }
            });

            $this->success();
        });
    }
}
