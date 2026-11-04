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
        'an object literal declines' => ['{ a: string }', true, null],
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
