<?php

namespace Miguilim\FilamentAutoPanel\Generators\Objects;

class Column
{
    protected string $type;

    protected ?int $length;

    protected int $decimalPlaces;

    protected bool $objectCast = false;

    protected bool $integer;

    public function __construct(protected array $data, ?string $cast = null, ?string $driver = null)
    {
        $typeName = strtolower($data['type_name']);
        $definition = strtolower($data['type']);
        $this->integer = in_array($typeName, ['integer', 'int', 'int4', 'tinyint', 'smallint', 'int2', 'mediumint', 'bigint', 'int8'], true);

        $this->type = match (true) {
            preg_match('/^tinyint\(1\)(?: unsigned)?$/', $definition) === 1 => 'boolean',
            in_array($definition, ['varchar(max)', 'nvarchar(max)']) => 'textarea',
            default => match ($typeName) {
                'bool', 'boolean', 'bit' => 'boolean',
                'json', 'jsonb' => 'json',
                // Preserve the existing zero-decimal display formatting for floating-point columns.
                'integer', 'int', 'int4', 'tinyint', 'smallint', 'int2', 'mediumint', 'bigint', 'int8',
                'float', 'real', 'float4', 'double', 'float8' => 'integer',
                'decimal', 'numeric' => 'decimal',
                'text', 'tinytext', 'mediumtext', 'longtext', 'ntext' => 'textarea',
                'varchar', 'char', 'bpchar', 'nvarchar', 'nchar', 'string' => 'string',
                'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'timestamp', 'timestamptz' => 'datetime',
                default => $typeName,
            },
        };

        preg_match('/\((\d+)\)/', $definition, $length);
        $this->length = isset($length[1]) && (int) $length[1] > 0 && $this->type !== 'boolean' ? (int) $length[1] : null;

        if ($this->length === null && in_array($driver, ['mysql', 'mariadb'])) {
            $this->length = match ($typeName) {
                'tinytext' => 255,
                'text' => 65_535,
                'mediumtext' => 16_777_215,
                'longtext' => 4_294_967_295,
                default => null,
            };
        }

        preg_match('/\(\d+,\s*(\d+)\)/', $definition, $scale);
        $this->decimalPlaces = (int) ($scale[1] ?? 0);

        if ($cast !== null) {
            [$castType, $parameters] = array_pad(explode(':', $cast, 2), 2, null);

            if ($castType === 'encrypted' && $parameters !== null) {
                $castType = $parameters;
            }

            $this->objectCast = $castType === 'object';
            $this->integer = match ($castType) {
                'int', 'integer', 'timestamp' => true,
                'float', 'double', 'real', 'decimal' => false,
                default => $this->integer,
            };

            $this->type = match ($castType) {
                'bool', 'boolean' => 'boolean',
                'array', 'json', 'object', 'collection' => 'json',
                'int', 'integer', 'timestamp', 'float', 'double', 'real' => 'integer',
                'decimal' => 'decimal',
                'string', 'encrypted', 'hashed' => 'string',
                'date', 'immutable_date' => 'date',
                'datetime', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime' => 'datetime',
                default => $this->type,
            };

            if ($castType === 'decimal' && $parameters !== null && ctype_digit($parameters)) {
                $this->decimalPlaces = (int) $parameters;
            }
        }
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNotNull(): bool
    {
        return ! $this->data['nullable'];
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function getDecimalPlaces(): int
    {
        return $this->type === 'decimal' ? $this->decimalPlaces : 0;
    }

    public function isObjectCast(): bool
    {
        return $this->objectCast;
    }

    public function isInteger(): bool
    {
        return $this->type === 'integer' && $this->integer;
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['integer', 'decimal']);
    }
}
