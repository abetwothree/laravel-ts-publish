<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

class TypeScriptMap
{
    /** @var array<string, string|(callable(): string)>|null */
    protected static ?array $map = null;

    /**
     * @return array<string, string|(callable(): string)>
     */
    public function gather(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $map = [
            // Laravel built-in cast classes (FQN — resolved before class_exists check)
            \Illuminate\Database\Eloquent\Casts\AsStringable::class => 'string',
            \Illuminate\Database\Eloquent\Casts\AsUri::class => 'string',
            \Illuminate\Database\Eloquent\Casts\AsBinary::class => 'string',
            \Illuminate\Database\Eloquent\Casts\AsFluent::class => 'object',
            \Illuminate\Database\Eloquent\Casts\AsArrayObject::class => 'Array<unknown>',
            \Illuminate\Database\Eloquent\Casts\AsCollection::class => 'Array<unknown>',
            \Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject::class => 'Array<unknown>',
            \Illuminate\Database\Eloquent\Casts\AsEncryptedCollection::class => 'Array<unknown>',
            \Illuminate\Database\Eloquent\Casts\AsEnumArrayObject::class => 'Array<unknown>',
            \Illuminate\Database\Eloquent\Casts\AsEnumCollection::class => 'Array<unknown>',
            \Illuminate\Database\Eloquent\Collection::class => 'Record<string, unknown>',
            \Illuminate\Support\Collection::class => 'Array<unknown> | Record<string, unknown>',

            // Array types
            'array' => 'Array<unknown>',

            // Number types
            'bigint' => 'number',
            'decimal' => 'number',
            'double' => 'number',
            'float' => 'number',
            'integer' => 'number',
            'numeric' => 'number',
            'int' => 'number',
            'mediumint' => 'number',
            'smallint' => 'number',
            'year' => 'number',
            'real' => 'number',

            // Boolean types
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'tinyint' => 'boolean',

            // JSON types
            'json' => 'object',
            'jsonb' => 'object',
            'object' => 'object',
            'collection' => 'Array<unknown>',

            // String types
            'char' => 'string',
            'character' => 'string',
            'enum' => 'string',
            'longtext' => 'string',
            'mediumtext' => 'string',
            'string' => 'string',
            'text' => 'string',
            'varchar' => 'string',
            'encrypted' => 'string',
            'uuid' => 'string',
            'guid' => 'string',
            'hashed' => 'string',

            // Date and time types
            'date' => fn () => $this->validateDate(),
            'immutable_date' => fn () => $this->validateDate(),
            'datetime' => fn () => $this->validateDate(),
            'immutable_datetime' => fn () => $this->validateDate(),
            'immutable_custom_datetime' => fn () => $this->validateDate(),
            'timestamp' => fn () => $this->validateDate(),
            'time' => 'string',
            'timetz' => 'string',
            'timestamptz' => 'string',

            'null' => 'null',
            'mixed' => 'unknown',
        ];

        /** @var array<string, string|(callable(): string)> $merged */
        $merged = array_change_key_case(array_merge(
            $map,
            config()->array('ts-publish.custom_ts_mappings', []),
        ), CASE_LOWER);

        return self::$map = $merged;
    }

    protected function validateDate(): string
    {
        return config()->boolean('ts-publish.timestamps_as_date', false) ? 'Date' : 'string';
    }
}
