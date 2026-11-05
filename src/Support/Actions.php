<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoCreateAction;
use Miguilim\FilamentAutoPanel\Filament\Actions\AutoEditAction;


class Actions
{
    public static function select(array $actions, string $placement): array
    {
        $selected = [];

        foreach ($actions as $action) {
            if (! ($action->{$placement} ?? false)) {
                continue;
            }

            // Lifecycle closures bind to their action; getActions() supplies fresh instances.
            if ($placement === 'showOnBulkAction') {
                $action->bulk()->accessSelectedRecords();
            }

            $selected[] = $action;
        }

        return $selected;
    }

    /** First occurrence wins, allowing explicit actions to replace defaults by name. */
    public static function merge(array ...$groups): array
    {
        $actions = [];

        foreach ($groups as $group) {
            foreach ($group as $action) {
                if ($action instanceof Action) {
                    $actions['action:' . $action->getName()] ??= $action;
                } else {
                    $actions[] = $action;
                }
            }
        }

        return array_values($actions);
    }

    public static function table(bool $readOnly, bool $softDeletes, bool $editable = false): array
    {
        $actions = $editable && ! $readOnly ? [AutoEditAction::make()] : [];
        $actions[] = ViewAction::make();

        if ($softDeletes && ! $readOnly) {
            $actions[] = RestoreAction::make();
        }

        return $actions;
    }

    public static function bulk(bool $readOnly, bool $softDeletes): array
    {
        if ($readOnly) {
            return [];
        }

        return $softDeletes
            ? [DeleteBulkAction::make(), RestoreBulkAction::make(), ForceDeleteBulkAction::make()]
            : [DeleteBulkAction::make()];
    }

    public static function listing(bool $readOnly): array
    {
        return $readOnly ? [] : [AutoCreateAction::make()];
    }

    public static function viewing(bool $readOnly, bool $softDeletes): array
    {
        if ($readOnly) {
            return [];
        }

        $actions = [AutoEditAction::make(), DeleteAction::make()];

        return $softDeletes ? [...$actions, ForceDeleteAction::make(), RestoreAction::make()] : $actions;
    }
}
