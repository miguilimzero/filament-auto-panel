<?php

namespace Miguilim\FilamentAutoPanel\Filament\Filters;

use Filament\Forms\Components\Select;
use Filament\QueryBuilder\Constraints\RelationshipConstraint\Operators\IsRelatedToOperator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Miguilim\FilamentAutoPanel\Generators\RelationshipGuesser;
use Miguilim\FilamentAutoPanel\Support\RelationshipOptionLabel;

class RelatedRecordOperator extends IsRelatedToOperator
{
    public function getFormSchema(): array
    {
        $schema = parent::getFormSchema();
        $related = $this->getRelationship()->getRelated();
        $title = $this->getTitleAttribute();
        $userColumn = $related instanceof Authenticatable
            ? UserColumn::make("{$this->getConstraint()->getRelationshipName()}.{$title}")
            : null;

        foreach ($schema as $field) {
            if (! $field instanceof Select) {
                continue;
            }

            $field
                ->getOptionLabelFromRecordUsing(static fn (Model $record): string => RelationshipOptionLabel::format($record, $title, $userColumn))
                ->allowHtml()
                ->native(false)
                ->searchable($this->isSearchable() ? RelationshipGuesser::guessModelSearchColumns($related, $title) : false);
        }

        return $schema;
    }
}
