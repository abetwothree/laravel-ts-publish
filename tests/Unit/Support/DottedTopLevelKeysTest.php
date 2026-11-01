<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\DottedTopLevelKeys;

/**
 * A top-level entry of the given type, required unless a `?` ends it.
 *
 * @return array{type: string, optional: bool}
 */
function topLevelEntry(string $type): array
{
    return str_ends_with($type, '?')
        ? ['type' => substr($type, 0, -1), 'optional' => true]
        : ['type' => $type, 'optional' => false];
}

it('leaves keys without a dot, and a signature holding one, as they are', function () {
    $props = ['a' => topLevelEntry('string'), '[key: `a.${string}`]' => topLevelEntry('number'), 7 => topLevelEntry('boolean')];

    expect(DottedTopLevelKeys::nest($props))->toBe($props);
});

it('prints a dotted key from pieces that name the key whose type each prints, after the keys without a dot', function () {
    expect(DottedTopLevelKeys::nest(['auth.user' => topLevelEntry('User'), 'n' => topLevelEntry('number')]))->toBe([
        'n' => topLevelEntry('number'),
        'auth' => ['type' => '{ user: User }', 'optional' => false, 'pieces' => [
            ['text' => '{ user: ', 'from' => null],
            ['text' => 'User', 'from' => 'auth.user'],
            ['text' => ' }', 'from' => null],
        ]],
    ]);
});

it('prints the object a written key holds from its own text and the writes\'', function () {
    expect(DottedTopLevelKeys::nest(['a' => topLevelEntry('{ id: Post }'), 'a.b' => topLevelEntry('User')])['a']['pieces'] ?? null)
        ->toBe([
            ['text' => '{ id: Post', 'from' => 'a'],
            ['text' => '; b: ', 'from' => null],
            ['text' => 'User', 'from' => 'a.b'],
            ['text' => ' }', 'from' => null],
        ]);
});

it('prints a list a written key holds from its own element\'s text', function () {
    expect(DottedTopLevelKeys::nest(['a' => topLevelEntry('Post[]'), 'a.b' => topLevelEntry('User')])['a']['pieces'] ?? null)
        ->toBe([
            ['text' => 'Record<number, ', 'from' => null],
            ['text' => 'Post', 'from' => 'a'],
            ['text' => '> & { b: ', 'from' => null],
            ['text' => 'User', 'from' => 'a.b'],
            ['text' => ' }', 'from' => null],
        ]);
});

// Arr::set() writes into an array it finds on the path and replaces any other value; Inertia sends a later key last.
it('writes each dotted key into its path as Arr::set() does', function (array $props, array $nested) {
    expect(array_map(
        static fn (array $entry): string => $entry['type'].($entry['optional'] ? '?' : ''),
        DottedTopLevelKeys::nest(array_map(topLevelEntry(...), $props)),
    ))->toBe($nested);
})->with([
    'a new key' => [['a.b' => 'number'], ['a' => '{ b: number }']],
    'a new key every write may leave out' => [['a.b' => 'number?', 'a.c' => 'string?'], ['a' => '{ b?: number; c?: string }?']],
    'into an object' => [['a' => '{ id: number }', 'a.b' => 'string'], ['a' => '{ id: number; b: string }']],
    'into a later object' => [['a.b' => 'string', 'a' => '{ id: number }'], ['a' => '{ id: number; b: string }']],
    'into an object the write may leave alone' => [
        ['a' => '{ id: number }', 'a.b' => 'string?'],
        ['a' => '{ id: number; b?: string }'],
    ],
    'into an object that may be absent' => [
        ['a' => '{ id: number }?', 'a.b' => 'string'],
        ['a' => '{ id: number; b: string } | { b: string }'],
    ],
    'into an object that may be null' => [
        ['a' => '{ id: number } | null', 'a.b' => 'string'],
        ['a' => '{ id: number; b: string } | { b: string }'],
    ],
    'over a scalar' => [['a' => 'string', 'a.b' => 'number'], ['a' => '{ b: number }']],
    'over a scalar the write may leave alone' => [['a' => 'string', 'a.b' => 'number?'], ['a' => '{ b?: number } | string']],
    'over an empty array' => [['a' => 'never[]', 'a.b' => 'number'], ['a' => '{ b: number }']],
    'over a key of an object' => [
        ['a' => '{ k: number; j: string }', 'a.k' => 'string'],
        ['a' => "Omit<{ k: number; j: string }, 'k'> & { k: string }"],
    ],
    'over a key of an object the write may leave alone' => [
        ['a' => '{ k: number; j: string }', 'a.k' => 'string?'],
        ['a' => "Omit<{ k: number; j: string }, 'k'> & { k: string | { k: number; j: string }[\"k\"] }"],
    ],
    'into a key of an object' => [
        ['a' => '{ b: { x: number }; y: string }', 'a.b.c' => 'number'],
        ['a' => "Omit<{ b: { x: number }; y: string }, 'b'> & { b: { b: { x: number }; y: string }[\"b\"] & { c: number } }"],
    ],
    'into a list' => [['a' => 'Post[]', 'a.b' => 'number'], ['a' => 'Record<number, Post> & { b: number }']],
    'into a list that may be null' => [
        ['a' => 'Post[] | null', 'a.b' => 'number'],
        ['a' => 'Record<number, Post> & { b: number } | { b: number }'],
    ],
    'beside a class' => [['a' => 'User', 'a.b' => 'number'], ['a' => '(User & { b: number }) | { b: number }']],
    'beside a union' => [['a' => 'User | Post', 'a.b' => 'number'], ['a' => '((User | Post) & { b: number }) | { b: number }']],
    'beside a class the write may leave alone' => [
        ['a' => 'User', 'a.b' => 'number?'],
        ['a' => '(User & { b?: number }) | { b?: number } | User'],
    ],
    'beside unknown' => [['a' => 'unknown', 'a.b' => 'number'], ['a' => 'Record<string, unknown> & { b: number }']],
    'a later dotted key into an earlier one' => [
        ['a.b' => '{ x: number }', 'a.b.c' => 'number'],
        ['a' => '{ b: { x: number; c: number } }'],
    ],
    'a later dotted key over an earlier one' => [['a.b.c' => 'number', 'a.b' => '{ x: number }'], ['a' => '{ b: { x: number } }']],
    'numeric and empty segments' => [
        ['1.5' => 'string', 'a..b' => 'number', '.c' => 'number', 'd.' => 'number'],
        [1 => '{ "5": string }', 'a' => '{ "": { b: number } }', '' => '{ c: number }', 'd' => '{ "": number }'],
    ],
    'into an int key' => [[1 => 'string', '1.5' => 'number'], [1 => '{ "5": number }']],
    'a later dotted key that may be left out over an earlier one' => [
        ['a.b.c' => 'number', 'a.b' => '{ y: number }?'],
        ['a' => '{ b: { y: number } | { c: number } }'],
    ],
    'a later dotted key that may be left out over an earlier one that may be too' => [
        ['a.b.c' => 'number?', 'a.b' => 'string?'],
        ['a' => '{ b?: string | { c?: number } }?'],
    ],
    'a template signature covering the head' => [
        ['[key: `${string}_tag`]' => 'string | undefined', 'x_tag.b' => 'number'],
        ['[key: `${string}_tag`]' => 'string | undefined | { b: number }', 'x_tag' => '{ b: number }'],
    ],
    'a number signature covering a numeric head' => [
        ['[key: number]' => 'string', '1.5' => 'number'],
        ['[key: number]' => 'string | { "5": number }', 1 => '{ "5": number }'],
    ],
    'a signature covering no head' => [
        ['[key: `${string}_tag`]' => 'string', 'a.b' => 'number'],
        ['[key: `${string}_tag`]' => 'string', 'a' => '{ b: number }'],
    ],
    'two signatures covering the head' => [
        ['[key: `${string}_tag`]' => 'string', '[key: string]' => 'string', 'x_tag.b' => 'number'],
        [
            '[key: `${string}_tag`]' => 'string | { b: number }',
            '[key: string]' => 'string | { b: number }',
            'x_tag' => '{ b: number }',
        ],
    ],
    'an unknown signature' => [
        ['[key: string]' => 'unknown', 'a.b' => 'number'],
        ['[key: string]' => 'unknown', 'a' => '{ b: number }'],
    ],
]);

// Inertia replaces a resource or a prop object under a dotted key with `[]`, as it replaces a scalar.
it('writes a dotted key over a closed value, kept only where every write may be left out', function () {
    $closed = ['type' => '{ data: R }', 'optional' => false, 'closed' => true];
    $print = static fn (array $props): array => array_map(
        static fn (array $entry): string => $entry['type'].($entry['optional'] ? '?' : ''),
        DottedTopLevelKeys::nest($props),
    );

    expect($print(['a' => $closed, 'a.b' => topLevelEntry('number')]))->toBe(['a' => '{ b: number }'])
        ->and($print(['a' => $closed, 'a.b' => topLevelEntry('number?')]))->toBe(['a' => '{ b?: number } | { data: R }'])
        ->and($print(['a.x' => $closed, 'a.x.b' => topLevelEntry('number')]))->toBe(['a' => '{ x: { b: number } }'])
        ->and($print(['a' => [...$closed, 'type' => 'unknown'], 'a.b' => topLevelEntry('number')]))->toBe(['a' => '{ b: number }']);
});

it('prints a signature a head widens from its own text and the head\'s', function () {
    $nested = DottedTopLevelKeys::nest(['[key: string]' => topLevelEntry('Post'), 'a.b' => topLevelEntry('User')]);

    expect($nested['[key: string]']['pieces'] ?? null)->toBe([
        ['text' => 'Post', 'from' => '[key: string]'],
        ['text' => ' | { b: ', 'from' => null],
        ['text' => 'User', 'from' => 'a.b'],
        ['text' => ' }', 'from' => null],
    ]);
});
