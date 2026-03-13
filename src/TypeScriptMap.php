<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsBinary;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Casts\AsUri;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Support\Collection;

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
            AsStringable::class => 'string',
            AsUri::class => 'string',
            AsBinary::class => 'string',
            AsFluent::class => 'object',
            AsArrayObject::class => 'unknown[]',
            AsCollection::class => 'unknown[]',
            AsEncryptedArrayObject::class => 'unknown[]',
            AsEncryptedCollection::class => 'unknown[]',
            AsEnumArrayObject::class => 'unknown[]',
            AsEnumCollection::class => 'unknown[]',
            \Illuminate\Database\Eloquent\Collection::class => 'Record<string, unknown>',
            Collection::class => 'unknown[] | Record<string, unknown>',

            // Array types
            'array' => 'unknown[]',

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
            'collection' => 'unknown[]',

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
            Carbon::class => fn () => $this->validateDate(),
            CarbonImmutable::class => fn () => $this->validateDate(),
            SupportCarbon::class => fn () => $this->validateDate(),

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
