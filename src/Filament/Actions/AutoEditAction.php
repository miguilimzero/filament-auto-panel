<?php

namespace Miguilim\FilamentAutoPanel\Filament\Actions;

use Exception;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Miguilim\FilamentAutoPanel\AutoResource;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Table;

class AutoEditAction extends EditAction
{
    public bool $showOnBulkAction = false;

    public bool $showOnTable = false;

    public bool $showOnViewPage = true;

    public bool $showOnListPage = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fillForm(function (Model $record, AutoResource $resource, ?Table $table): array {
            if ($table?->getRelationship() && $table?->getRelationship() instanceof BelongsToMany) {
                throw new Exception('BelongsToMany relationship is not supported');
            }

            return ($resource::isIntrusive())
                ? $record->setHidden([])->attributesToArray()
                : $record->attributesToArray();
        });

        $this->action(function (): void {
            $this->process(function (array $data, Model $record, AutoResource $resource, ?Table $table) {
                if ($table?->getRelationship() && $table?->getRelationship() instanceof BelongsToMany) {
                    throw new Exception('BelongsToMany relationship is not supported');
                }

                if ($resource::isIntrusive()) {
                    $record->forceFill($data)->save();
                } else {
                    $record->update($data);
                }
            });

            $this->success();
        });
    }
}
