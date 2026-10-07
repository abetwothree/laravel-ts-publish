<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

/**
 * The type a relation aggregate (`withSum()`, `withAvg()`, `withMin()`, `withMax()`) reaches a payload with: what the
 * connection's PDO extension returns for an aggregate of the column's database type, under Laravel's default options.
 *
 * @internal
 */
final class AggregateValueType
{
    /**
     * Each driver's PHP type for an aggregate's SQL result kind. The `sqlsrv` driver runs on pdo_sqlsrv, which returns
     * a number as a string by default, or on pdo_dblib, which returns it as a number, so it proves only a string.
     */
    private const array RESULT_TYPES = [
        'sqlite' => ['number' => 'number', 'decimal' => 'number', 'text' => 'string'],
        'mysql' => ['number' => 'number', 'decimal' => 'string', 'text' => 'string'],
        'mariadb' => ['number' => 'number', 'decimal' => 'string', 'text' => 'string'],
        'pgsql' => ['number' => 'number', 'decimal' => 'string', 'text' => 'string'],
        'sqlsrv' => ['text' => 'string'],
    ];

    /**
     * The aggregate's TypeScript type without its null arm, `number` or `string`, or null when nothing proves one.
     */
    public static function of(string $function, string $columnType, string $driver): ?string
    {
        $kind = self::resultKind($function, self::columnKind($columnType), $driver);

        return $kind === null ? null : self::RESULT_TYPES[$driver][$kind] ?? null;
    }

    /**
     * The SQL result an aggregate of a column kind gives: `number` (an integer or a float), `decimal` (DECIMAL or
     * NUMERIC) or `text` (a date, a time or a string), or null for a function or column kind no rule covers.
     */
    private static function resultKind(string $function, ?string $kind, string $driver): ?string
    {
        // MIN() and MAX() keep the column's type, while SUM() and AVG() widen an exact column to DECIMAL, except
        // PostgreSQL's SUM() of a 2- or 4-byte integer, which is a bigint.
        return match ($function) {
            'min', 'max' => match ($kind) {
                'integer', 'bigint', 'float' => 'number',
                'decimal' => 'decimal',
                'temporal', 'text' => 'text',
                default => null,
            },
            'sum', 'avg' => match ($kind) {
                'integer' => $function === 'sum' && $driver === 'pgsql' ? 'number' : 'decimal',
                'bigint', 'decimal' => 'decimal',
                'float' => 'number',
                default => null,
            },
            default => null,
        };
    }

    /**
     * The kind of column the head word of a schema type names, spelled as `Schema::getColumns()` reports it on each
     * driver, such as `int unsigned`, `numeric(12,2)`, `double precision` or `timestamp(0) without time zone`.
     */
    private static function columnKind(string $type): ?string
    {
        return match (preg_match('/^[a-z][a-z0-9]*/', $type, $head) === 1 ? $head[0] : '') {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'int2', 'int4', 'serial', 'smallserial',
            'year' => 'integer',
            'bigint', 'int8', 'bigserial' => 'bigint',
            'decimal', 'numeric', 'dec', 'fixed', 'money', 'smallmoney' => 'decimal',
            'float', 'double', 'real', 'float4', 'float8' => 'float',
            'date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'timestamp', 'timestamptz', 'time',
            'timetz' => 'temporal',
            'char', 'varchar', 'character', 'nchar', 'nvarchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'ntext',
            'citext', 'enum', 'set' => 'text',
            default => null,
        };
    }
}
