<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;

describe('arms', function () {
    test('maps each arm, leaving a `null` arm out unless asked to keep it', function () {
        expect(ArrayArms::arms('string[] | null', fn (string $arm): string => $arm))->toBe(['string[]'])
            ->and(ArrayArms::arms('string[] | null', fn (string $arm): string => $arm, false))->toBe(['string[]', 'null'])
            ->and(ArrayArms::arms('null', fn (string $arm): string => $arm))->toBe([]);
    });

    test('declines the whole type where one arm declines', function () {
        expect(ArrayArms::arms('A | number', fn (string $arm): ?string => $arm === 'number' ? null : $arm))->toBeNull();
    });
});

describe('isArray and isKeyed', function () {
    test('a list, a tuple, a Record or an object literal is an array; only the last two are keyed', function (string $arm, bool $array, bool $keyed) {
        expect(ArrayArms::isArray($arm))->toBe($array)
            ->and(ArrayArms::isKeyed($arm))->toBe($keyed);
    })->with([
        'a Record' => ['Record<string, number>', true, true],
        'an object literal' => ['{ a: string }', true, true],
        'the empty object literal' => ['{}', true, true],
        'an object literal intersected with a Record' => ['{ a: string } & Record<number, B>', true, true],
        'a list' => ['string[]', true, false],
        'an Array' => ['Array<T>', true, false],
        'a tuple' => ['[A, B]', true, false],
        'a scalar' => ['string', false, false],
    ]);
});

describe('valuesOf', function () {
    test('the list array_values() makes of one arm', function (string $arm, ?string $values) {
        expect(ArrayArms::valuesOf($arm))->toBe($values);
    })->with([
        'an object literal lists its member types' => ['{ a: string; b: number }', '(string | number)[]'],
        'the empty object literal lists nothing' => ['{}', 'never[]'],
        'a Record lists its values' => ['Record<string, number>', 'number[]'],
        'a list stays' => ['(A | B)[]', '(A | B)[]'],
        'an Array stays' => ['Array<T>', 'Array<T>'],
        'a tuple stays' => ['[A, B]', '[A, B]'],
        'a scalar declines' => ['string', null],
    ]);
});

describe('filtered', function () {
    test('what array_filter() keeps of one arm', function (string $arm, bool $dropsNull, ?string $kept) {
        expect(ArrayArms::filtered($arm, $dropsNull))->toBe($kept);
    })->with([
        'a list may gap into an object' => ['string[]', true, 'string[] | Record<number, string>'],
        'a list drops its nulls' => ['(string | null)[]', true, 'string[] | Record<number, string>'],
        'a callback keeps the nulls' => ['(string | null)[]', false, '(string | null)[] | Record<number, string | null>'],
        'a list of nulls keeps nothing' => ['null[]', true, 'never[] | Record<number, never>'],
        'a Record keeps its keys' => ['Record<string, number | null>', true, 'Record<string, number>'],
        'a tuple declines' => ['[A, B]', true, null],
        'an object literal keeps each member optional' => ['{ a: string; b?: number }', true, '{ a?: string; b?: number }'],
        'an object literal drops a member\'s nulls' => ['{ a: string | null }', true, '{ a?: string }'],
        'an object literal drops a null-only member' => ['{ a: string; b: null }', true, '{ a?: string }'],
        'a callback keeps a null-only member' => ['{ a: string; b: null }', false, '{ a?: string; b?: null }'],
        'an object literal of null-only members keeps nothing' => ['{ a: null }', true, 'never[]'],
        'the empty object keeps nothing' => ['{}', true, 'never[]'],
        'int keys' => ['{ "1": string; "2": string }', true, '{ "1"?: string; "2"?: string }'],
        'an int key beside a named one declines' => ['{ "1": string; b: string }', true, null],
        'key 0 declines' => ['{ "0": string; "2": string }', true, null],
        'an index signature declines' => ['{ [key: string]: string }', true, null],
    ]);
});

describe('listElement', function () {
    test('the element of a type whose one arm is a list', function (string $type, ?string $element) {
        expect(ArrayArms::listElement($type))->toBe($element);
    })->with([
        'a list' => ['string[]', 'string'],
        'a list of a union' => ['(A | B)[]', 'A | B'],
        'an Array' => ['Array<T>', 'T'],
        'a readonly list' => ['readonly X[]', 'X'],
        'a tuple declines' => ['[A, B]', null],
        'the empty tuple declines' => ['[]', null],
        'a nullable list declines' => ['string[] | null', null],
        'an intersection declines' => ['A & B[]', null],
        'an arrow returning a list declines' => ['() => B[]', null],
    ]);
});

describe('elementOf', function () {
    test('the value each element of one arm holds', function (string $arm, ?string $element) {
        expect(ArrayArms::elementOf($arm))->toBe($element);
    })->with([
        'a list' => ['string[]', 'string'],
        'an Array' => ['Array<T>', 'T'],
        'a tuple joins its members' => ['[number, string, number]', 'number | string'],
        'the empty tuple holds none' => ['[]', 'never'],
        'no element' => ['never[]', 'never'],
        'a Record holds its values' => ['Record<string, number>', 'number'],
        'an object literal joins its members' => ['{ a: number; b?: string }', 'number | string'],
        'an empty object holds none' => ['{}', 'never'],
        'an arm nothing types' => ['unknown', 'unknown'],
        'a scalar declines' => ['string', null],
        'an intersection declines' => ['{ a: string } & { b: string }', null],
    ]);
});

describe('mapped', function () {
    test('what array_map() makes of one arm, keyed as it was', function (string $arm, ?string $mapped) {
        expect(ArrayArms::mapped($arm, 'R'))->toBe($mapped);
    })->with([
        'a list stays a list' => ['string[]', 'R[]'],
        'a tuple re-lists' => ['[number, string]', 'R[]'],
        'a Record keeps its keys' => ['Record<string, number>', 'Record<string, R>'],
        'an object literal keeps its members' => ['{ a: number; "1"?: string }', '{ a: R; "1"?: R }'],
        'an empty object is the empty list' => ['{}', 'never[]'],
        'an arm nothing types may be either' => ['unknown', 'R[] | Record<string, R>'],
        'a scalar declines' => ['string', null],
        'an intersection declines' => ['{ a: string } & { b: string }', null],
    ]);

    test('a union value is parenthesized before the list suffix binds', function () {
        expect(ArrayArms::mapped('string[]', 'A | B'))->toBe('(A | B)[]');
    });
});

describe('keysOf, keysList and keysListOf', function () {
    test('the keys one arm holds: a list\'s numbers only where it is keyed by ints, never an open literal\'s', function (string $arm, bool $listed, ?string $keys) {
        expect(ArrayArms::keysOf($arm, $listed))->toBe($keys);
    })->with([
        'a list keyed by ints' => ['string[]', true, 'number'],
        'a list keyed by anything, as PHPDoc\'s T[]' => ['string[]', false, null],
        'a native array' => ['unknown[]', false, null],
        'a tuple' => ['[A, B]', false, 'number'],
        'the empty list' => ['never[]', false, 'never'],
        'a Record keyed by strings' => ['Record<string, number>', false, 'string'],
        'a Record keyed by numbers' => ['Record<number, number>', false, 'number'],
        'a Record keyed by an alias' => ['Record<StatusType, number>', false, null],
        'a literal printed without the keys it cannot name' => ['Record<string, unknown>', false, null],
        'an object literal, which may be open' => ['{ a: string }', false, null],
        'a scalar' => ['string', false, null],
    ]);

    test('the list a type\'s keys make, a null arm left out', function (string $type, bool $listed, ?string $keys) {
        expect(ArrayArms::keysList($type, $listed))->toBe($keys);
    })->with([
        'a nullable Record' => ['Record<string, string> | null', false, 'string[]'],
        'a list beside a Record, keyed by ints' => ['number[] | Record<string, number>', true, '(number | string)[]'],
        'a list beside a Record, keyed by anything' => ['number[] | Record<string, number>', false, null],
        'null alone' => ['null', false, null],
        'the empty list beside a Record' => ['never[] | Record<string, number>', false, 'string[]'],
    ]);

    test('the list array_keys() makes of the keys PHP holds', function (array $keys, string $list) {
        expect(ArrayArms::keysListOf($keys))->toBe($list);
    })->with([
        'keys 0..n-1' => [[0, 1, 2], 'number[]'],
        'no key' => [[], 'never[]'],
        'ints out of order' => [[1, 0], '(1 | 0)[]'],
        'names and ints' => [['a', 200, -3], "('a' | 200 | -3)[]"],
        'an int past a JSON number\'s exact range' => [[PHP_INT_MAX, 'a'], "(number | 'a')[]"],
    ]);

    test('whether a type holds a list arm', function () {
        expect(ArrayArms::holdsList('string[] | null'))->toBeTrue()
            ->and(ArrayArms::holdsList('Record<string, number> | [A, B]'))->toBeFalse();
    });
});
