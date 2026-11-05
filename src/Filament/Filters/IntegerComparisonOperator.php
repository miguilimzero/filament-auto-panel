<?php

namespace Miguilim\FilamentAutoPanel\Filament\Filters;

use Filament\Forms\Components\TextInput;
use Filament\QueryBuilder\Constraints\Operators\Operator;
use Illuminate\Database\Eloquent\Builder;

class IntegerComparisonOperator extends Operator
{
    public function getLabel(): string
    {
        return __($this->translationKey('label'));
    }

    public function getSummary(): string
    {
        return __($this->translationKey('summary'), [
            'attribute' => $this->getConstraint()->getAttributeLabel(),
            'number' => $this->getIntegerSetting(),
        ]);
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('number')
                ->label(__('filament-query-builder::query-builder.operators.number.form.number.label'))
                ->inputMode('numeric')
                ->rule('string')
                ->regex('/^-?[0-9]+$/D')
                ->required(),
        ];
    }

    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        $number = $this->getIntegerSetting();

        if ($number === null) {
            return $query;
        }

        $comparison = match ($this->getName()) {
            'isMin' => $this->isInverse() ? '<' : '>=',
            'isMax' => $this->isInverse() ? '>' : '<=',
            'equals' => $this->isInverse() ? '!=' : '=',
        };

        return $query->where($qualifiedColumn, $comparison, $number);
    }

    protected function getIntegerSetting(): ?string
    {
        $number = $this->getSettings()['number'] ?? null;

        if (is_int($number)) {
            return (string) $number;
        }

        return is_string($number) && preg_match('/^-?[0-9]+$/D', $number) === 1
            ? $number
            : null;
    }

    protected function translationKey(string $context): string
    {
        $name = match ($this->getName()) {
            'isMin' => 'is_min',
            'isMax' => 'is_max',
            'equals' => 'equals',
        };
        $direction = $this->isInverse() ? 'inverse' : 'direct';

        return "filament-query-builder::query-builder.operators.number.{$name}.{$context}.{$direction}";
    }
}
