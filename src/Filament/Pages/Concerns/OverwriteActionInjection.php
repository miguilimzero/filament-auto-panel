<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages\Concerns;

use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;

trait OverwriteActionInjection
{
    public function callMountedAction(array $arguments = []): mixed
    {
        $action = $this->getMountedAction();

        if (! $action) {
            return null;
        }

        if ($action->isDisabled()) {
            return null;
        }

        $action->arguments([
            ...Arr::last($this->mountedActionsArguments),
            ...$arguments,
        ]);

        $form = $this->getMountedActionForm();

        $result = null;

        $originallyMountedActions = $this->mountedActions;

        try {
            if ($this->mountedActionHasForm()) {
                $action->callBeforeFormValidated();

                $action->formData($form->getState());

                $action->callAfterFormValidated();
            }

            $action->callBefore();

            // Is this really the better way to inject the record model
            // to the action closure?
            $result = $action->call([
                'form' => $form,
                'record' => $this->getMountedActionFormModel(), 
            ]);

            $result = $action->callAfter() ?? $result;

            $this->afterActionCalled();
        } catch (Halt $exception) {
            return null;
        } catch (Cancel $exception) {
        }

        $action->resetArguments();
        $action->resetFormData();

        // If the action was replaced while it was being called,
        // we don't want to unmount it.
        if ($originallyMountedActions !== $this->mountedActions) {
            $action->clearRecordAfter();

            return null;
        }

        if (store($this)->has('redirect')) {
            return $result;
        }

        $this->unmountAction();

        return $result;
    }
}