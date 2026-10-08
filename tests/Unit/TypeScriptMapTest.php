<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\TypeScriptMap;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Support\Collection;

beforeEach(function () {
    // Reset the static cache before each test
    $reflection = new ReflectionClass(TypeScriptMap::class);
    $prop = $reflection->getProperty('map');
    $prop->setValue(null, null);
});

test('gather returns an array with expected type mappings', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map)
        ->toBeArray()
        ->toHaveKey('string')
        ->toHaveKey('integer')
        ->toHaveKey('boolean')
        ->toHaveKey('array')
        ->toHaveKey('json')
        ->toHaveKey('date')
        ->toHaveKey(strtolower(Collection::class))
        ->toHaveKey(strtolower(Illuminate\Database\Eloquent\Collection::class))
        ->toHaveKey('null')
        ->and($map['string'])->toBe('string')
        ->and($map['integer'])->toBe('number')
        ->and($map['boolean'])->toBe('boolean')
        ->and($map['array'])->toBe('unknown[]')
        ->and($map[strtolower(Collection::class)])->toBe('unknown[] | Record<string, unknown>')
        ->and($map[strtolower(Illuminate\Database\Eloquent\Collection::class)])->toBe('Record<string, unknown>')
        ->and($map['null'])->toBe('null');
});

test('gather returns all keys in lowercase', function () {
    $map = (new TypeScriptMap)->gather();

    foreach (array_keys($map) as $key) {
        expect($key)->toBe(strtolower($key));
    }
});

test('gather caches the result on subsequent calls', function () {
    $map1 = (new TypeScriptMap)->gather();
    $map2 = (new TypeScriptMap)->gather();

    expect($map1)->toBe($map2);
});

test('gather merges custom_ts_mappings from config', function () {
    config()->set('ts-publish.custom_ts_mappings', [
        'my_custom_type' => 'MyCustomTsType',
    ]);

    $map = (new TypeScriptMap)->gather();

    expect($map)->toHaveKey('my_custom_type')
        ->and($map['my_custom_type'])->toBe('MyCustomTsType');
});

test('custom_ts_mappings override default mappings', function () {
    config()->set('ts-publish.custom_ts_mappings', [
        'string' => 'CustomString',
    ]);

    $map = (new TypeScriptMap)->gather();

    expect($map['string'])->toBe('CustomString');
});

// castToTsType() defers a cast these keys name to toTsType(), so they must be the keys the map it reads was built with.
test('customKeys names the custom_ts_mappings keys of the cached map, lowercased as gather() merges them', function () {
    config()->set('ts-publish.custom_ts_mappings', ['Timestamp' => 'boolean', 'decimal:2' => 'number']);
    $keys = (new TypeScriptMap)->customKeys();
    config()->set('ts-publish.custom_ts_mappings', []);

    expect($keys)->toBe(['timestamp' => true, 'decimal:2' => true])
        ->and((new TypeScriptMap)->customKeys())->toBe($keys);
});

test('date types resolve to string by default', function () {
    config()->set('ts-publish.timestamps_as_date', false);

    $map = (new TypeScriptMap)->gather();

    // Date types are callables — invoke them
    expect(($map['date'])())->toBe('string')
        ->and(($map['datetime'])())->toBe('string')
        ->and(($map['timestamp'])())->toBe('string');
});

test('date types resolve to Date when timestamps_as_date is true', function () {
    config()->set('ts-publish.timestamps_as_date', true);

    $map = (new TypeScriptMap)->gather();

    expect(($map['date'])())->toBe('Date')
        ->and(($map['datetime'])())->toBe('Date')
        ->and(($map['timestamp'])())->toBe('Date');
});

test('maps network column types to string', function (string $dbType) {
    $map = (new TypeScriptMap)->gather();

    expect($map[$dbType])->toBe('string');
})->with(['inet', 'cidr', 'macaddr', 'macaddr8']);

test('maps tsvector columns to string', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['tsvector'])->toBe('string');
});

test('maps bare castable classes to their TS shapes', function (string $castableClass, string $expected) {
    $map = (new TypeScriptMap)->gather();

    expect($map[strtolower($castableClass)])->toBe($expected);
})->with([
    [AsArrayObject::class, 'unknown[] | Record<string, unknown>'],
    // Both sibling ArrayObject casts hydrate an ArrayObject too, so a list payload must stay legal.
    [AsEncryptedArrayObject::class, 'unknown[] | Record<string, unknown>'],
    [AsEnumArrayObject::class, 'unknown[] | Record<string, unknown>'],
    [AsStringable::class, 'string'],
    [AsCollection::class, 'unknown[]'],
    [AsEncryptedCollection::class, 'unknown[]'],
    [AsEnumCollection::class, 'unknown[]'],
]);

test('maps bare iterable to unknown[], matching the bare array entry', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['iterable'])->toBe('unknown[]');
});

test('maps a genuine bare tinyint to number, not boolean', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['tinyint'])->toBe('number');
});

test('maps the tinyint(1) display-width convention to boolean', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['tinyint(1)'])->toBe('boolean');
});

test('maps new binary types to string', function (string $dbType) {
    $map = (new TypeScriptMap)->gather();

    expect($map[$dbType])->toBe('string');
})->with(['binary', 'varbinary', 'blob', 'bytea', 'tinyblob', 'mediumblob', 'longblob']);

test('maps new legacy string types to string', function (string $dbType) {
    $map = (new TypeScriptMap)->gather();

    expect($map[$dbType])->toBe('string');
})->with(['tinytext', 'nvarchar', 'nchar', 'ntext', 'xml', 'interval', 'uniqueidentifier']);

test('maps set to string, not an array', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['set'])->toBe('string');
});

test('maps new number types to number', function (string $dbType) {
    $map = (new TypeScriptMap)->gather();

    expect($map[$dbType])->toBe('number');
})->with(['money', 'smallmoney', 'serial', 'bigserial', 'smallserial', 'double precision']);

test('maps bit to boolean', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['bit'])->toBe('boolean');
});

test('maps datetimeoffset to string', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['datetimeoffset'])->toBe('string');
});

test('datetime2 and smalldatetime resolve to string by default, like datetime', function (string $dbType) {
    config()->set('ts-publish.timestamps_as_date', false);

    $map = (new TypeScriptMap)->gather();

    expect(($map[$dbType])())->toBe('string');
})->with(['datetime2', 'smalldatetime']);

test('datetime2 and smalldatetime resolve to Date when timestamps_as_date is true, like datetime', function (string $dbType) {
    // SQL Server's dateTime($precision)/timestamp($precision) emit datetime2($precision) — the
    // same logical column as bare 'datetime', so a hard 'string' here would silently opt SQL
    // Server timestamp columns out of the timestamps_as_date config toggle.
    config()->set('ts-publish.timestamps_as_date', true);

    $map = (new TypeScriptMap)->gather();

    expect(($map[$dbType])())->toBe('Date');
})->with(['datetime2', 'smalldatetime']);

test('maps geometry and geography to unknown', function (string $dbType) {
    $map = (new TypeScriptMap)->gather();

    expect($map[$dbType])->toBe('unknown');
})->with(['geometry', 'geography']);

test('maps MySQL geometry subtypes to unknown, same as bare geometry', function (string $dbType) {
    $map = (new TypeScriptMap)->gather();

    expect($map[$dbType])->toBe('unknown');
})->with(['point', 'linestring', 'polygon', 'geometrycollection', 'multipoint', 'multilinestring', 'multipolygon']);

test('maps vector to number[]', function () {
    $map = (new TypeScriptMap)->gather();

    expect($map['vector'])->toBe('number[]');
});
