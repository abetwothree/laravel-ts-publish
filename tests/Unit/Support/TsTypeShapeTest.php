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

    test('reads a string or template literal whole, its escapes and its text alike', function (string $type, array $expected) {
        expect(TsTypeShape::splitTopLevel($type, ['|']))->toBe($expected);
    })->with([
        'a pipe in template text' => ['`a|b` | null', ['`a|b`', 'null']],
        'a null in template text' => ['`x | null | y`', ['`x | null | y`']],
        'a brace in template text' => ['`a{b` | null', ['`a{b`', 'null']],
        'an escaped quote ending no string' => ["'it\\'s' | 'b'", ["'it\\'s'", "'b'"]],
        'an escaped quote before a pipe' => ["'a\\'|b' | null", ["'a\\'|b'", 'null']],
    ]);

    test('gives an arrow function each pipe after it on its level', function (string $type, array $expected) {
        expect(TsTypeShape::splitTopLevel($type, ['|']))->toBe($expected);
    })->with([
        'a union return type' => ['() => A | null', ['() => A | null']],
        'an arrow after another arm' => ['B | (a: string) => C | D', ['B', '(a: string) => C | D']],
        'a parenthesized arrow' => ['(() => A) | null', ['(() => A)', 'null']],
    ]);
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

    test('reads past a template literal holding a member separator', function () {
        expect(TsTypeShape::memberType('{ x: `a;b`; y: number }', 'y'))->toBe('number')
            ->and(TsTypeShape::memberType('{ x: `a;b`; y: number }', 'x'))->toBe('`a;b`')
            ->and(TsTypeShape::memberType("{ 'a\\'b': string; c: number }", 'c'))->toBe('number');
    });

    // Probe S2direct: a key is matched by the name its printed spelling decodes to, never by its raw text.
    test('reads a member by the key its printed key names, escaped or quoted', function () {
        expect(TsTypeShape::memberType("{ 'it\\'s': number }", "it's"))->toBe('number')
            ->and(TsTypeShape::memberType('{ "a\\": b": string; other: number }', 'other'))->toBe('number')
            ->and(TsTypeShape::memberType('{ "a\\/b": string }', 'a/b'))->toBe('string')
            ->and(TsTypeShape::memberType('{ "\\u00e9"?: string }', 'é'))->toBe('string')
            ->and(TsTypeShape::memberType('{ "a\\": b": string }', 'a": b'))->toBe('string')
            ->and(TsTypeShape::memberType("{ 'it\\'s': number }", "it\\'s"))->toBeNull();
    });

    test('reads a bare decimal key by the key TypeScript names, and declines a spelling it names otherwise', function () {
        expect(TsTypeShape::memberType('{ 1.5: number }', '1.5'))->toBe('number')
            ->and(TsTypeShape::memberType('{ 1.5?: string }', '1.5'))->toBe('string')
            ->and(TsTypeShape::memberType('{ 01.5: number }', '01.5'))->toBeNull();
    });

    test('reads a tuple member only by an index a list holds', function () {
        expect(TsTypeShape::memberType('[number, string]', '01'))->toBeNull()
            ->and(TsTypeShape::memberType('[number, string]', '-1'))->toBeNull()
            ->and(TsTypeShape::memberType('[number, string]', '9223372036854775808'))->toBeNull();
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
            ->and(TsTypeShape::elementType('(string | number)[]'))->toBe('string | number')
            ->and(TsTypeShape::elementType('`a|b`[]'))->toBe('`a|b`');
    });

    test('reads a list only where its `[]` or `Array<…>` takes the whole arm', function (string $type, ?string $element) {
        expect(TsTypeShape::elementType($type))->toBe($element);
    })->with([
        'an intersection ending in a list' => ['A & B[]', null],
        'an intersection ending in the empty tuple' => ['(A | B) & []', null],
        'an arrow returning a list' => ['() => B[]', null],
        'an Array intersected with a Record' => ['Array<A> & Record<string, B>', null],
        'an Array of two arguments' => ['Array<A, B>', null],
        'a list of an intersection' => ['(A & B)[]', 'A & B'],
        'a list of an arrow' => ['(() => B)[]', '() => B'],
        'a list of a parenthesized arrow beside one' => ['((() => A) | (() => B))[]', '(() => A) | (() => B)'],
        'a list arm after an intersection arm' => ['A & B[] | C[]', 'C'],
    ]);

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
        'a function returning a union' => [['() => A | null'], '(() => A | null)[]'],
        'an object holding a union' => [['{ a: A | B }'], '{ a: A | B }[]'],
        'a template literal holding a pipe' => [['`a|b`'], '`a|b`[]'],
        'a literal with an escaped quote' => [["'it\\'s|x'"], "'it\\'s|x'[]"],
        'an untyped element beside a typed one' => [['string', 'unknown'], 'unknown[]'],
    ]);
});

describe('isWrapped', function () {
    test('answers whether only the last character closes the opening bracket', function (string $type, string $open, string $close, bool $wrapped) {
        expect(TsTypeShape::isWrapped($type, $open, $close))->toBe($wrapped);
    })->with([
        'an object literal' => ['{ a: string }', '{', '}', true],
        'an object holding an arrow function' => ['{ a: () => void; b: string }', '{', '}', true],
        'a closer in template text' => ['{ a: `a}b`; c: number }', '{', '}', true],
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
        'a brace in template text' => ['{ k: `a{b`; n: number }', [['k', '`a{b`'], ['n', 'number']]],
        'a separator in template text' => ['{ k: `a;b`, n: number }', [['k', '`a;b`'], ['n', 'number']]],
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
        'a template literal' => ['`${string}_label`', false],
        'a brace in template text' => ['`a{b`', false],
        'an object beside a template literal' => ['`a{b` | { a: string }', true],
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
        'a JSON-escaped int' => ['"\\u0031"', true],
        'a key quoted around an escaped quote' => ["'1\\''", false],
        'a name' => ['week', false],
        'a string signature' => ['[key: string]', false],
    ]);
});

describe('recordArguments', function () {
    test('reads the key and value of one Record, a template literal holding a comma read whole', function (string $type, ?array $arguments) {
        expect(TsTypeShape::recordArguments($type))->toBe($arguments);
    })->with([
        'a Record' => ['Record<string, number>', ['string', 'number']],
        'a template key holding a comma and a pipe' => ['Record<`${A | B},x`, V>', ['`${A | B},x`', 'V']],
        'a Record beside null' => ['Record<string, number> | null', null],
        'no Record' => ['Map<string, number>', null],
    ]);
});

describe('tupleMembers', function () {
    test('reads each member of a tuple, a template literal holding a comma read whole', function (string $tuple, array $members) {
        expect(TsTypeShape::tupleMembers($tuple))->toBe($members);
    })->with([
        'two members' => ['[string, number]', ['string', 'number']],
        'a template literal holding a comma' => ['[`a,b`, number]', ['`a,b`', 'number']],
        'an optional union' => ['[number, (string | null)?]', ['number', 'string | null']],
    ]);

    test('says which tuple member is optional, a rest element read as written', function (string $tuple, array $elements) {
        expect(TsTypeShape::tupleElements($tuple))->toBe($elements);
    })->with([
        'two members' => ['[string, number]', [['string', false], ['number', false]]],
        'an optional member' => ['[string, number?]', [['string', false], ['number', true]]],
        'an optional union' => ['[number, (string | null)?]', [['number', false], ['string | null', true]]],
        'a rest element' => ['[number, ...string[]]', [['number', false], ['...string[]', false]]],
        'the empty tuple' => ['[]', []],
    ]);
});

describe('unionArm', function () {
    test('parenthesizes an arm holding a top-level arrow, so a union beside it stays outside its return type', function (string $arm, string $expected) {
        expect(TsTypeShape::unionArm($arm))->toBe($expected);
    })->with([
        'an arrow function' => ['() => void', '(() => void)'],
        'one returning a union' => ['(a: string) => A | B', '((a: string) => A | B)'],
        'a constructor type' => ['new () => A', '(new () => A)'],
        'a parenthesized arrow' => ['(() => void)', '(() => void)'],
        'an object holding an arrow' => ['{ fn: () => void }', '{ fn: () => void }'],
        'a list of arrows' => ['(() => void)[]', '(() => void)[]'],
        'an arrow in a string literal' => ["'() => void'", "'() => void'"],
        'a union' => ['A | B', 'A | B'],
    ]);
});

describe('groupedArm', function () {
    test('parenthesizes an arm holding a top-level intersection or arrow, so a union reads as its arms', function (string $arm, string $expected) {
        expect(TsTypeShape::groupedArm($arm))->toBe($expected);
    })->with([
        'an intersection' => ['A & B', '(A & B)'],
        'an arrow' => ['() => A & B', '(() => A & B)'],
        'an intersection inside an object' => ['{ a: A & B }', '{ a: A & B }'],
        'a plain arm' => ['A[]', 'A[]'],
    ]);
});

describe('postfixOperand', function () {
    test('parenthesizes a type holding a top-level union, intersection or arrow before a postfix `[]` or `?`', function (string $type, string $expected) {
        expect(TsTypeShape::postfixOperand($type))->toBe($expected);
    })->with([
        'a union' => ['A | null', '(A | null)'],
        'an intersection' => ['A & B', '(A & B)'],
        'an arrow' => ['() => A', '(() => A)'],
        'a union inside an object' => ['{ a: A | null }', '{ a: A | null }'],
        'a union in a string literal' => ["'a|b'", "'a|b'"],
        'a plain type' => ['A', 'A'],
    ]);
});
