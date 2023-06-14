<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages\Concerns;

use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;

trait OverwriteActionInjection
{
    public function callMountedAction(?string $arguments = null)
    {
        $action = $this->getMountedAction();

        if (! $action) {
            return;
        }

        if ($action->isDisabled()) {
            return;
        }

        $action->arguments($arguments ? json_decode($arguments, associative: true) : []);

        $form = $this->getMountedActionForm();

        $result = null;

        try {
            if ($action->hasForm()) {
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
        } catch (Halt $exception) {
            return;
        } catch (Cancel $exception) {
        }

        if (filled($this->redirectTo)) {
            return $result;
        }

        $this->mountedAction = null;

        $action->resetArguments();
        $action->resetFormData();

        $this->dispatchBrowserEvent('close-modal', [
            'id' => 'page-action',
        ]);

        return $result;
    }
}