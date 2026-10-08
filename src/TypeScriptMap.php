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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class TypeScriptMap
{
    /** @var array<string, string|(callable(): string)>|null */
    protected static ?array $map = null;

    /**
     * The `custom_ts_mappings` keys the cached map was built with, lowercased as it merges them.
     *
     * @var array<string, true>
     */
    protected static array $customKeys = [];

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
            AsArrayObject::class => 'unknown[] | Record<string, unknown>',
            AsCollection::class => 'unknown[]',
            // The three As*ArrayObject casts all hydrate an ArrayObject, whose jsonSerialize()
            // returns getArrayCopy() verbatim — a list payload stays a JSON array, so the object
            // shape alone would reject it.
            AsEncryptedArrayObject::class => 'unknown[] | Record<string, unknown>',
            AsEncryptedCollection::class => 'unknown[]',
            AsEnumArrayObject::class => 'unknown[] | Record<string, unknown>',
            AsEnumCollection::class => 'unknown[]',
            EloquentCollection::class => 'Record<string, unknown>',
            Collection::class => 'unknown[] | Record<string, unknown>',

            // Array types
            'array' => 'unknown[]',
            'iterable' => 'unknown[]',

            // Number types
            'bigint' => 'number',
            'decimal' => 'number',
            'double' => 'number',
            'double precision' => 'number',
            'float' => 'number',
            'integer' => 'number',
            'numeric' => 'number',
            'int' => 'number',
            'mediumint' => 'number',
            'smallint' => 'number',
            'year' => 'number',
            'real' => 'number',
            'number' => 'number',
            'money' => 'number',
            'smallmoney' => 'number',
            'serial' => 'number',
            'bigserial' => 'number',
            'smallserial' => 'number',
            // A genuine small integer (MySQL/SQL Server tinyInteger()) — the display-width-1
            // convention that means boolean instead is its own exact key, in Boolean types below.
            'tinyint' => 'number',

            // Boolean types
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'bit' => 'boolean',
            // $table->boolean() emits tinyint(1) on MySQL/SQLite; the display width is what marks
            // it boolean rather than a genuine tinyint (see 'tinyint' above).
            'tinyint(1)' => 'boolean',

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
            'tinytext' => 'string',
            'varchar' => 'string',
            'nvarchar' => 'string',
            'nchar' => 'string',
            'ntext' => 'string',
            'xml' => 'string',
            'interval' => 'string',
            'encrypted' => 'string',
            'uuid' => 'string',
            'uniqueidentifier' => 'string',
            'guid' => 'string',
            'hashed' => 'string',
            // MySQL returns a matched SET as a comma-joined string, not an array.
            'set' => 'string',

            // Binary types
            'binary' => 'string',
            'varbinary' => 'string',
            'blob' => 'string',
            'bytea' => 'string',
            'tinyblob' => 'string',
            'mediumblob' => 'string',
            'longblob' => 'string',

            // Date and time types
            'date' => fn () => $this->validateDate(),
            'immutable_date' => fn () => $this->validateDate(),
            'datetime' => fn () => $this->validateDate(),
            'immutable_datetime' => fn () => $this->validateDate(),
            'immutable_custom_datetime' => fn () => $this->validateDate(),
            'timestamp' => fn () => $this->validateDate(),
            // SQL Server's dateTime($precision)/timestamp($precision) emit datetime2($precision) —
            // the same logical column as bare 'datetime', so it must follow the same config toggle.
            // smalldatetime is a legacy-only precision-less sibling; kept consistent with it.
            'datetime2' => fn () => $this->validateDate(),
            'smalldatetime' => fn () => $this->validateDate(),
            Carbon::class => fn () => $this->validateDate(),
            CarbonImmutable::class => fn () => $this->validateDate(),
            SupportCarbon::class => fn () => $this->validateDate(),

            'time' => 'string',
            'timetz' => 'string',
            'timestamptz' => 'string',
            'datetimeoffset' => 'string',

            // Network address types (Postgres inet/cidr/macaddr, MySQL equivalents)
            'inet' => 'string',
            'cidr' => 'string',
            'macaddr' => 'string',
            'macaddr8' => 'string',

            // Postgres full-text search vector
            'tsvector' => 'string',

            // Spatial types — raw WKB is a binary string, ST_AsGeoJSON() is an object, and Laravel
            // returns whichever the driver defaults to, so 'unknown' is honest rather than a guess.
            'geometry' => 'unknown',
            'geography' => 'unknown',
            // MySQL's geometry(subtype: '...') writes the subtype itself as the native type name
            // instead of 'geometry' — same honesty rationale as the two entries above.
            'point' => 'unknown',
            'linestring' => 'unknown',
            'polygon' => 'unknown',
            'geometrycollection' => 'unknown',
            'multipoint' => 'unknown',
            'multilinestring' => 'unknown',
            'multipolygon' => 'unknown',

            // Vector types — pgvector and MySQL 9 both serialize a vector as a JSON array of floats.
            'vector' => 'number[]',

            'null' => 'null',
            'mixed' => 'unknown',

            // PHPStan / PHPDoc primitives
            'never' => 'never',
            'void' => 'void',
            'true' => 'true',
            'false' => 'false',
            'numeric-string' => 'string',
            'array-key' => 'string | number',
            'scalar' => 'string | number | boolean',
        ];

        $customMappings = Config::array('ts-publish.custom_ts_mappings', []);

        /** @var array<string, string|(callable(): string)> $merged */
        $merged = array_change_key_case(array_merge($map, $customMappings), CASE_LOWER);

        /** @var array<string, true> $customKeys */
        $customKeys = array_fill_keys(array_keys(array_change_key_case($customMappings, CASE_LOWER)), true);
        self::$customKeys = $customKeys;

        return self::$map = $merged;
    }

    /**
     * The `custom_ts_mappings` keys of the map gather() returns, lowercased as it merges them.
     *
     * @return array<string, true>
     */
    public function customKeys(): array
    {
        $this->gather();

        return self::$customKeys;
    }

    protected function validateDate(): string
    {
        return Config::boolean('ts-publish.timestamps_as_date', false) ? 'Date' : 'string';
    }
}
