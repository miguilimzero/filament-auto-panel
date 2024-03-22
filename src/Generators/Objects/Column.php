<?php

namespace Miguilim\FilamentAutoPanel\Generators\Objects;

class Column
{
    public function __construct(protected array $data)
    {
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getType(): string
    {
        return $this->data['type_name'];
    }

    public function getNotNull(): bool
    {
        return ! $this->data['nullable'];
    }

    public function getLength(): int
    {
        preg_match('/\((\d+)\)/', $this->data['type'], $matches);

        return (int) ($matches[1] ?? 0);
    }

    public function getDecimalPlaces(): int
    {
        if ($this->getType() !== 'decimal') {
            return 0;
        }

        preg_match('/\d+,\s*(\d+)/', $this->data['type'], $matches);

        return (int) ($matches[1] ?? 0);
    }

    public function isNumeric(): bool
    {
        return in_array($this->getType(), ['integer','decimal']);
    }
}