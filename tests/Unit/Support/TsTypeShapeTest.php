<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

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

    test('splits a template literal whose text holds escaped quotes at its own pipes', function (string $type, array $expected) {
        expect(TsTypeShape::splitTopLevel($type, ['|']))->toBe($expected);
    })->with([
        'escaped double quotes around a placeholder' => ['`\\"${string}\\"` | null', ['`\\"${string}\\"`', 'null']],
        'escaped double quotes in plain text' => ['`say \\"hi\\"` | null', ['`say \\"hi\\"`', 'null']],
        'escaped single quotes around a placeholder' => ['`\\\'${string}\\\'` | null', ['`\\\'${string}\\\'`', 'null']],
    ]);

    test('reads a backtick as template text, so one inside a placeholder cannot close a span', function (string $type, array $expected) {
        expect(TsTypeShape::splitTopLevel($type, ['|']))->toBe($expected);
    })->with([
        'a single-quoted backtick' => ["`\${'`'}` | null", ["`\${'`'}`", 'null']],
        'a double-quoted backtick' => ['`${"`"}` | undefined', ['`${"`"}`', 'undefined']],
        'a nested template with a pipe' => ['`${`a|b`}` | null', ['`${`a|b`}`', 'null']],
    ]);

    test('floors depth at zero so an unmatched closing bracket still splits what follows', function () {
        expect(TsTypeShape::splitTopLevel('a) | b', ['|']))->toBe(['a)', 'b']);
    });
});

describe('memberType', function () {
    test('reads a member out of an inline object literal', function () {
        expect(TsTypeShape::memberType('{ minimum: number; maximum: null }', 'maximum'))->toBe('null')
            ->and(TsTypeShape::memberType('{ items: Record<string, number>; tags: string[] }', 'items'))->toBe('Record<string, number>')
            ->and(TsTypeShape::memberType('{ a: { b: string; c: number }; d: string }', 'd'))->toBe('string')
            ->and(TsTypeShape::memberType('{ a: { b: string; c: number }; d: string }', 'a'))->toBe('{ b: string; c: number }')
            ->and(TsTypeShape::memberType('{ "2fa"?: boolean, other: string }', '2fa'))->toBe('boolean')
            ->and(TsTypeShape::memberType('{ "a:b": string; other: number }', 'a:b'))->toBe('string')
            ->and(TsTypeShape::memberType('{ a: `\\"${string}\\"`; b: Record<string, number> }', 'b'))->toBe('Record<string, number>')
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

describe('admits', function () {
    test('admits a candidate every value of which the type holds', function (string $type, string $candidate) {
        expect(TsTypeShape::admits($type, $candidate))->toBeTrue();
    })->with([
        'itself' => ['User', 'User'],
        'one arm of a union' => ['string | null', 'string'],
        'a union within a wider union' => ['string | number | boolean', 'string | number'],
        'a literal of its primitive' => ['string', "'draft' | 'published'"],
        'a number and a boolean literal' => ['number | boolean', '1 | true'],
        'a parenthesized union arm' => ['string | number | null', '(string | number) | null'],
        'an array of an admitted element' => ['(string | null)[]', 'string[]'],
        'the empty list' => ['string[]', 'never[]'],
        'a literal under a string-keyed record' => ['Record<string, number | string>', '{ a: number; b: string }'],
        'a record with the same key' => ['Record<string, number | null>', 'Record<string, number>'],
        'a present key for an optional one' => ['{ a: number; b?: string }', '{ a: number; b: string }'],
        'an absent optional key' => ['{ a: number; b?: string }', '{ a: number }'],
        'a key the type does not name' => ['{ a: number }', '{ a: number; c: boolean }'],
        'a quoted key' => ["{ 'a-b': number }", "{ 'a-b': number }"],
        'anything under unknown' => ['unknown', '{ a: number }'],
    ]);

    test('declines a candidate it cannot show the type holds', function (string $type, string $candidate) {
        expect(TsTypeShape::admits($type, $candidate))->toBeFalse();
    })->with([
        'a wider union' => ['string', 'string | null'],
        'another primitive' => ['number', 'string'],
        'another name' => ['User', 'Post'],
        'unknown' => ['string', 'unknown'],
        'an unknown member' => ['{ a: string }', '{ a: unknown }'],
        'a missing required key' => ['{ a: number; b: string }', '{ a: number }'],
        'an optional key for a required one' => ['{ a: number; b: string }', '{ a: number; b?: string }'],
        'a number-keyed record over a literal' => ['Record<number, string>', '{ a: string }'],
        'an array of another element' => ['string[]', 'number[]'],
    ]);
});
