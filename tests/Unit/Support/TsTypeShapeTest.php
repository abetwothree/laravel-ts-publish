<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

describe('isListLike', function () {
    test('recognises every array spelling', function (string $type) {
        expect(TsTypeShape::isListLike($type))->toBeTrue();
    })->with([
        'string[]',
        'readonly string[]',
        'Array<number>',
        'ReadonlyArray<{ a: number }>',
        '[]',
        '[string, number]',
        'string[] | null',
        '(string | number)[] | undefined',
    ]);

    test('rejects objects, scalars, aliases, and mixed unions', function (string $type) {
        expect(TsTypeShape::isListLike($type))->toBeFalse();
    })->with([
        'Record<string, boolean>',
        '{ a: number }',
        'string',
        'unknown',
        'Foo',
        'string[] | Foo',
        'string[] | Record<string, string>',
        'null',
    ]);
});

describe('isObjectLike', function () {
    test('recognises object literals, Record, and index signatures', function (string $type) {
        expect(TsTypeShape::isObjectLike($type))->toBeTrue();
    })->with([
        '{ a: number }',
        '{ [key: string]: boolean }',
        'Record<string, boolean>',
        '{ a: number } | null',
        'Record<string, never>',
    ]);

    test('rejects arrays, scalars, aliases, intersections, and mixed unions', function (string $type) {
        expect(TsTypeShape::isObjectLike($type))->toBeFalse();
    })->with([
        'string[]',
        'string',
        'Foo',
        '{ a: number } | string[]',
        // Exactly what wrapAsMaybeKeyedArray() emits for array<array-key, X> (src/LaravelTsPublish.php ~1201).
        'string[] | Record<string, string>',
        // Intersections are treated as opaque; the safe fallback is to leave [] alone.
        'Foo & { a: number }',
        'null',
    ]);
});

describe('splitTopLevel', function () {
    test('splits only at depth zero and keeps quoted literals whole', function () {
        expect(TsTypeShape::splitTopLevel('{ a: string; b: number | null } | null', ['|']))
            ->toBe(['{ a: string; b: number | null }', 'null'])
            ->and(TsTypeShape::splitTopLevel("'a|b' | 'c'", ['|']))->toBe(["'a|b'", "'c'"])
            // The pipe here is protected only by quote tracking; no bracket depth applies.
            ->and(TsTypeShape::splitTopLevel('"a|b" | "c"', ['|']))->toBe(['"a|b"', '"c"'])
            // Double-quoted literals are live input: workbench/app/Models/User.php:54 emits '"light" | "dark"'.
            ->and(TsTypeShape::splitTopLevel('{ theme: "light" | "dark"; locale: string } | null', ['|']))
            ->toBe(['{ theme: "light" | "dark"; locale: string }', 'null'])
            ->and(TsTypeShape::splitTopLevel('{ "it\'s": string } | null', ['|']))->toBe(['{ "it\'s": string }', 'null'])
            ->and(TsTypeShape::splitTopLevel('a: string; b: [number, string]; c: Record<string, { d: number; e: string }>', [';', ',']))
            ->toBe(['a: string', 'b: [number, string]', 'c: Record<string, { d: number; e: string }>']);
    });

    test('floors depth at zero so an unmatched closing bracket still splits what follows', function () {
        expect(TsTypeShape::splitTopLevel('a) | b', ['|']))->toBe(['a)', 'b']);
    });

    test('topLevelPosition finds the first separator outside brackets and quotes', function () {
        expect(TsTypeShape::topLevelPosition('[key: string]: boolean', ':'))->toBe(13)
            ->and(TsTypeShape::topLevelPosition('"a:b": string', ':'))->toBe(5)
            ->and(TsTypeShape::topLevelPosition('Record<string, number>', ':'))->toBeNull();
    });
});

describe('memberType', function () {
    test('reads a member out of an inline object literal', function () {
        expect(TsTypeShape::memberType('{ minimum: number; maximum: null }', 'maximum'))->toBe('null')
            ->and(TsTypeShape::memberType('{ items: Record<string, number>; tags: string[] }', 'items'))->toBe('Record<string, number>')
            ->and(TsTypeShape::memberType('{ a: { b: string; c: number }; d: string }', 'd'))->toBe('string')
            ->and(TsTypeShape::memberType('{ a: { b: string; c: number }; d: string }', 'a'))->toBe('{ b: string; c: number }')
            ->and(TsTypeShape::memberType('{ "2fa"?: boolean, other: string }', '2fa'))->toBe('boolean')
            ->and(TsTypeShape::memberType('{ a: number } | null', 'a'))->toBe('number');
    });

    test('answers any key for Record and index-signature types', function () {
        expect(TsTypeShape::memberType('Record<string, { deep: number[] }>', 'anything'))->toBe('{ deep: number[] }')
            ->and(TsTypeShape::memberType('{ [key: string]: boolean }', 'anything'))->toBe('boolean');
    });

    test('returns null for a missing key or an opaque type', function () {
        expect(TsTypeShape::memberType('{ a: number }', 'b'))->toBeNull()
            ->and(TsTypeShape::memberType('Foo', 'a'))->toBeNull()
            ->and(TsTypeShape::memberType('string[]', 'a'))->toBeNull();
    });
});

describe('elementType', function () {
    test('reads the element type of arrays', function () {
        expect(TsTypeShape::elementType('string[]'))->toBe('string')
            ->and(TsTypeShape::elementType('readonly Foo[]'))->toBe('Foo')
            ->and(TsTypeShape::elementType('Array<{ a: number }>'))->toBe('{ a: number }')
            ->and(TsTypeShape::elementType('ReadonlyArray<number> | null'))->toBe('number')
            ->and(TsTypeShape::elementType('(string | number)[]'))->toBe('string | number');
    });

    test('returns null for non-arrays', function () {
        expect(TsTypeShape::elementType('Foo'))->toBeNull()
            ->and(TsTypeShape::elementType('Record<string, number>'))->toBeNull()
            ->and(TsTypeShape::elementType('[string, number]'))->toBeNull()
            ->and(TsTypeShape::elementType('[]'))->toBeNull();
    });
});
