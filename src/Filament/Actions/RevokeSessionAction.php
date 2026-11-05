<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Filament\RelationManagers\UserSessionsRelationManager;
use Miguilim\FilamentAutoPanel\Models\DatabaseSession;

class RevokeSessionAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revoke';
    }

    public function resolveRecordKey(Model | array $record): ?string
    {
        $manager = $this->getLivewire();

        return $record instanceof DatabaseSession && $manager instanceof UserSessionsRelationManager
            ? $manager->getTableRecordKey($record)
            : null;
    }
}
