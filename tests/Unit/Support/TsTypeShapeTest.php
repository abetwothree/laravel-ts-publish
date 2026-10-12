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

    // The `>` of `=>` closes no bracket.
    test('keeps an arrow function whole', function (string $type, array $expected) {
        expect(TsTypeShape::splitTopLevel($type, ['|']))->toBe($expected);
    })->with([
        'an arrow function returning a union' => ['{ fn: () => void | null } | null', ['{ fn: () => void | null }', 'null']],
        'a generic holding an arrow function' => ['Foo<() => A | B> | null', ['Foo<() => A | B>', 'null']],
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

    test('reads a tuple member by its position, without its optional marker', function () {
        expect(TsTypeShape::memberType('[Record<string, number>, number[]]', '0'))->toBe('Record<string, number>')
            ->and(TsTypeShape::memberType('[number, { a: string }?] | null', '1'))->toBe('{ a: string }')
            ->and(TsTypeShape::memberType('[number, (string | null)?]', '1'))->toBe('string | null')
            ->and(TsTypeShape::memberType('[number, string]', '2'))->toBeNull()
            ->and(TsTypeShape::memberType('[number, string]', 'a'))->toBeNull();
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

describe('listType', function () {
    test('lists the element types, each once, parenthesised when they need it', function (array $types, string $list) {
        expect(TsTypeShape::listType($types))->toBe($list);
    })->with([
        'one type' => [['string'], 'string[]'],
        'a repeated type' => [['string', 'string'], 'string[]'],
        'two types' => [['string', 'number'], '(string | number)[]'],
        'a union element' => [['string | null'], '(string | null)[]'],
        'an intersection element' => [['A & B'], '(A & B)[]'],
        'a function element' => [['() => void'], '(() => void)[]'],
        'an untyped element beside a typed one' => [['string', 'unknown'], 'unknown[]'],
    ]);
});

describe('isWrapped', function () {
    test('answers whether only the last character closes the opening bracket', function (string $type, string $open, string $close, bool $wrapped) {
        expect(TsTypeShape::isWrapped($type, $open, $close))->toBe($wrapped);
    })->with([
        'an object literal' => ['{ a: string }', '{', '}', true],
        'an object holding an arrow function' => ['{ a: () => void; b: string }', '{', '}', true],
        'a quoted closer' => ['{ "}": string }', '{', '}', true],
        'two objects intersected' => ['{ a: string } & { b: number }', '{', '}', false],
        'parentheses around a union' => ['(string | null)', '(', ')', true],
        'an arrow function between parentheses' => ['(a: string) => (b)', '(', ')', false],
        'an unclosed bracket' => ['{ { a: string }', '{', '}', false],
        'another opener' => ['string[]', '{', '}', false],
    ]);
});

describe('members', function () {
    test('splits an object literal into its printed keys and values', function (string $object, ?array $members) {
        expect(TsTypeShape::members($object))->toBe($members);
    })->with([
        'named and quoted keys' => ['{ id: number; "42"?: string }', [['id', 'number'], ['"42"?', 'string']]],
        'a nested object and an arrow function' => [
            '{ a: () => void; inner: { b: string } }',
            [['a', '() => void'], ['inner', '{ b: string }']],
        ],
        'a quoted key holding a colon' => ['{ "a:b": string }', [['"a:b"', 'string']]],
        'an index signature' => ['{ [key: number]: User }', [['[key: number]', 'User']]],
        'an empty object' => ['{}', []],
        'a member with no key' => ['{ a: string; b }', null],
        'an intersection' => ['{ a: string } & { b: number }', null],
        'no object' => ['Record<string, number>', null],
    ]);
});

describe('holdsKeyedObject', function () {
    test('answers whether an object literal or a number-keyed Record sits anywhere in the type', function (string $type, bool $holds) {
        expect(TsTypeShape::holdsKeyedObject($type))->toBe($holds);
    })->with([
        'an object literal' => ['{ "200": string }', true],
        'an object in a list' => ['{ a: string }[]', true],
        'an empty object' => ['{}', true],
        'a number-keyed Record' => ['Record<number, string>', true],
        'a Record holding an object' => ['Record<string, { "1": string }>', true],
        'a template literal, read as an object to be safe' => ['`${string}_label`', true],
        'a scalar' => ['string', false],
        'an empty list' => ['never[]', false],
        'a scalar list' => ['(string | number)[]', false],
        'a string-keyed Record of scalars' => ['Record<string, string>', false],
        'null' => ['null', false],
        'a named type' => ['UserResource', false],
        'a quoted brace' => ["'{' | \"Record<number, x>\"", false],
    ]);
});

describe('isNumericKey', function () {
    test('answers whether a printed or written key passes is_numeric()', function (string $key, bool $numeric) {
        expect(TsTypeShape::isNumericKey($key))->toBe($numeric);
    })->with([
        'a quoted int' => ['"42"', true],
        'a written int' => ['42', true],
        'an optional quoted decimal' => ["'1.5'?", true],
        'a number signature' => ['[key: number]', true],
        'a name' => ['week', false],
        'a string signature' => ['[key: string]', false],
    ]);
});
