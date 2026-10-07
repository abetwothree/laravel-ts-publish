<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\AggregateValueType;

// Each column type is spelled as Schema::getColumns() reports it on that driver. A DECIMAL or NUMERIC result is a string
// on MySQL, MariaDB and PostgreSQL, which never convert one, and SQLite stores no decimal, so it returns a number.
it('types an aggregate as the driver returns it', function (string $function, string $columnType, string $driver, ?string $type) {
    expect(AggregateValueType::of($function, $columnType, $driver))->toBe($type);
})->with([
    'sqlite sum(integer)' => ['sum', 'integer', 'sqlite', 'number'],
    'sqlite avg(integer)' => ['avg', 'integer', 'sqlite', 'number'],
    'sqlite sum(numeric)' => ['sum', 'numeric', 'sqlite', 'number'],
    'sqlite min(numeric)' => ['min', 'numeric', 'sqlite', 'number'],
    'sqlite max(float)' => ['max', 'float', 'sqlite', 'number'],
    'sqlite max(datetime)' => ['max', 'datetime', 'sqlite', 'string'],
    'sqlite min(varchar)' => ['min', 'varchar', 'sqlite', 'string'],
    'mysql sum(int unsigned)' => ['sum', 'int unsigned', 'mysql', 'string'],
    'mysql avg(int unsigned)' => ['avg', 'int unsigned', 'mysql', 'string'],
    'mysql sum(tinyint(1))' => ['sum', 'tinyint(1)', 'mysql', 'string'],
    'mysql min(int unsigned)' => ['min', 'int unsigned', 'mysql', 'number'],
    'mysql max(bigint unsigned)' => ['max', 'bigint unsigned', 'mysql', 'number'],
    'mysql max(year)' => ['max', 'year', 'mysql', 'number'],
    'mysql sum(decimal(10,2))' => ['sum', 'decimal(10,2)', 'mysql', 'string'],
    'mysql min(decimal(10,2))' => ['min', 'decimal(10,2)', 'mysql', 'string'],
    'mysql sum(double)' => ['sum', 'double', 'mysql', 'number'],
    'mysql avg(float)' => ['avg', 'float', 'mysql', 'number'],
    'mysql max(timestamp)' => ['max', 'timestamp', 'mysql', 'string'],
    'mariadb sum(int(11))' => ['sum', 'int(11)', 'mariadb', 'string'],
    'pgsql sum(integer)' => ['sum', 'integer', 'pgsql', 'number'],
    'pgsql sum(smallint)' => ['sum', 'smallint', 'pgsql', 'number'],
    'pgsql sum(bigint)' => ['sum', 'bigint', 'pgsql', 'string'],
    'pgsql avg(integer)' => ['avg', 'integer', 'pgsql', 'string'],
    'pgsql sum(numeric(12,2))' => ['sum', 'numeric(12,2)', 'pgsql', 'string'],
    'pgsql max(numeric(12,2))' => ['max', 'numeric(12,2)', 'pgsql', 'string'],
    'pgsql avg(double precision)' => ['avg', 'double precision', 'pgsql', 'number'],
    'pgsql sum(real)' => ['sum', 'real', 'pgsql', 'number'],
    'pgsql max(timestamp(0) without time zone)' => ['max', 'timestamp(0) without time zone', 'pgsql', 'string'],
    'pgsql min(character varying(255))' => ['min', 'character varying(255)', 'pgsql', 'string'],
    'sqlsrv max(datetime2(7))' => ['max', 'datetime2(7)', 'sqlsrv', 'string'],
    'sqlsrv sum(int), a string on pdo_sqlsrv and a number on pdo_dblib' => ['sum', 'int', 'sqlsrv', null],
    'sqlsrv min(decimal(10,2))' => ['min', 'decimal(10,2)', 'sqlsrv', null],
    'a driver the rule has no evidence for' => ['max', 'date', 'oracle', null],
    'sum() of a date, which proves nothing' => ['sum', 'date', 'mysql', null],
    'avg() of a string, which proves nothing' => ['avg', 'varchar(255)', 'mysql', null],
    'a boolean column' => ['max', 'boolean', 'pgsql', null],
    'a json column' => ['max', 'json', 'mysql', null],
    'a function the rule does not cover' => ['group_concat', 'varchar(255)', 'mysql', null],
    'a count, which is no column aggregate' => ['count', 'integer', 'mysql', null],
]);
