<?php

use AbeTwoThree\LaravelTsPublish\TypeScriptMap;
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
