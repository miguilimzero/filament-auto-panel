<?php

namespace Miguilim\FilamentAutoPanel\Filament\Filters;

use Filament\Forms\Components\TextInput;
use Filament\QueryBuilder\Constraints\Operators\Operator;
use Illuminate\Database\Eloquent\Builder;
use Miguilim\FilamentAutoPanel\Filament\Forms\NumericInput;
use Miguilim\FilamentAutoPanel\Support\DecimalFormatter;

class NumericComparisonOperator extends Operator
{
    protected bool $isInteger = false;

    public function integer(bool $condition = true): static
    {
        $this->isInteger = $condition;

        return $this;
    }

    public function isInteger(): bool
    {
        return $this->isInteger;
    }

    public function getLabel(): string
    {
        return __($this->translationKey('label'));
    }

    public function getSummary(): string
    {
        return __($this->translationKey('summary'), [
            'attribute' => $this->getConstraint()->getAttributeLabel(),
            'number' => $this->getNumberSetting(),
        ]);
    }

    public function getFormSchema(): array
    {
        $input = $this->isInteger()
            ? TextInput::make('number')->inputMode('numeric')->rule('string')->regex('/^-?[0-9]+$/D')
            : NumericInput::make('number');

        return [
            $input
                ->label(__('filament-query-builder::query-builder.operators.number.form.number.label'))
                ->required(),
        ];
    }

    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        $number = $this->getNumberSetting();

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

    protected function getNumberSetting(): ?string
    {
        $number = $this->getSettings()['number'] ?? null;

        if (is_int($number)) {
            return (string) $number;
        }

        if (! is_string($number)) {
            return null;
        }

        $valid = $this->isInteger()
            ? preg_match('/^-?[0-9]+$/D', $number) === 1
            : is_numeric($number) && DecimalFormatter::isWithinExpansionLimit($number);

        return $valid ? $number : null;
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
