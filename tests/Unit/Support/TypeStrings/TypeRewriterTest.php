<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeRewriter;

/**
 * Every type in the scanner corpus: golden types, hand-written cases with literals and arrows, and generated ones.
 *
 * @return list<string>
 */
function typeRewriterCorpus(): array
{
    /** @var list<array{t: string}> $rows */
    $rows = json_decode((string) file_get_contents(__DIR__.'/Fixtures/scanner-corpus.json'), true, flags: JSON_THROW_ON_ERROR);

    return array_column($rows, 't');
}

/**
 * An arm callback that drops each arm spelled `Y` and keeps every other.
 */
function droppingY(): Closure
{
    return static fn (string $arm): ?string => $arm === 'Y' ? null : $arm;
}

describe('identity', function () {
    test('returns a published type byte for byte when no callback changes it', function (string $type) {
        $visited = 0;
        $arm = static function (string $arm) use (&$visited): string {
            $visited++;

            return $arm;
        };
        $member = static fn (string $key, string $value): array => [$key, $value];

        expect(TypeRewriter::rewrite($type, $arm, $member))->toBe($type)
            ->and(TypeRewriter::rewrite($type))->toBe($type)
            ->and($visited)->toBeGreaterThan(0);
    })->with([
        'a union with an enum' => ['string | EnumsStatusType'],
        'string literals' => ["'Open' | 'Restricted'"],
        'a string literal holding an escaped quote' => ["'it\\'s' | 'b'"],
        'a list of literals, one holding `>`' => ["('>a' | 'b')[]"],
        'a list of a nullable union' => ['(string | null)[]'],
        'a list of a class or a boolean' => ['(User | boolean)[]'],
        'a list of lists' => ['app.models.Comment[][]'],
        'a tuple' => ['[number, string]'],
        'a list of tuples of unions' => ['[string | null, number | null][]'],
        'a tuple holding a list' => ['[(string | number)[], number]'],
        'a tuple with an optional element (a probe cell\'s)' => ['[string, number?][]'],
        'a Record' => ['Record<string, OrderItem>'],
        'the empty object' => ['Record<string, never>'],
        'an object intersected with a Record' => ['{ a: string } & Record<number, string>'],
        'a Record intersected with an object' => ['Record<number, User> & { flag: boolean }'],
        'a list of an intersection' => ['({ name?: string } & Record<string, string>)[]'],
        'a Record of an unspaced object' => ['Record<string, {title: string, content: string}>'],
        'an Omit beside an object' => ["Omit<User, 'flag'> & { flag: boolean }"],
        'a list of Omits' => ["(Omit<UserResource, 'note' | keyof ProfileResource> & Omit<ProfileResource, 'note'> & { note: string })[]"],
        'a nullable Pick' => ["Pick<User, 'id' | 'name'> | null"],
        'a Partial' => ['Partial<Post>'],
        'a list of AsEnum beside an enum' => ['AsEnum<typeof Status>[] | StatusType'],
        'a template-literal signature beside a key' => ['{ [key: `${string}_tag`]: string | number | undefined; price_tag: number }'],
        'a template-literal signature of unknown' => ['{ [key: `k${string}`]: unknown | undefined }'],
        'a template-literal signature holding a backslash' => ['{ [key: `${string}\\\\unit`]: string | undefined }'],
        'a template literal' => ['`${string}-${string}-${string}`'],
        'an object holding a template literal and a Record' => ['{ metadata: Record<string, {title: string, content: string}>; rating: `${number}` | null; status: StatusType }'],
        'nested objects' => ['{ in_app: { status_updates: boolean; comments: boolean }; digest: { status_updates: boolean; comments: boolean } }'],
        'double-quoted literals' => ['{ length: number; width: number; height: number; unit: "cm" | "in" }'],
        'a list of an optional key' => ['{ id?: unknown }[]'],
        'vague arms' => ['unknown[] | Record<string, unknown> | null'],
        'Inertia props with commas' => ['Inertia.SharedData & { either: AsEnum<typeof Role> | AsEnum<typeof Status>, mixed: AsEnum<typeof Status> | StatusType }'],
        'two objects' => ['{ id: number } | { id: number; title: string }'],
        'string literals holding `|`, `[`, `{` and `$gap1`' => ["'a | b' | '[x' | '{y' | '\$gap1'"],
        'a key spelled `$gap1`' => ['{ $gap1: string }'],
        'unspaced separators' => ['A|B'],
    ]);

    test('returns every corpus type byte for byte when no callback changes it', function () {
        $changed = array_filter(
            typeRewriterCorpus(),
            static fn (string $type): bool => TypeRewriter::rewrite(
                $type,
                static fn (string $arm): string => $arm,
                static fn (string $key, string $value): array => [$key, $value],
            ) !== $type,
        );

        expect(typeRewriterCorpus())->not->toBeEmpty()
            ->and($changed)->toBe([]);
    });
});

describe('arms', function () {
    test('passes each union arm at every depth to the callback, inside first', function () {
        $visited = [];

        TypeRewriter::rewrite('{ a: A | B }[] | C', static function (string $arm) use (&$visited): string {
            $visited[] = $arm;

            return $arm;
        });

        expect($visited)->toBe(['A', 'B', '{ a: A | B }', '{ a: A | B }[]', 'C']);
    });

    test('rewrites or drops an arm wherever it stands', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, droppingY()))->toBe($expected);
    })->with([
        'a top-level arm' => ['X | Y | null', 'X | null'],
        'an object member\'s arm' => ['{ a: X | Y; b: number }', '{ a: X; b: number }'],
        'an arm inside an Omit part' => ["Omit<{ by: X | Y }, 'id'> & { id: number }", "Omit<{ by: X }, 'id'> & { id: number }"],
        'an arm of a Record value' => ['Record<string, X | Y>', 'Record<string, X>'],
        'an arm of a Record key' => ['Record<string | Y, X>', 'Record<string, X>'],
        'a generic argument\'s arm' => ["Pick<User, 'id' | Y>", "Pick<User, 'id'>"],
        'an arm inside keyof' => ['Omit<A, keyof { a: X | Y }>', 'Omit<A, keyof { a: X }>'],
        'a tuple element\'s arm' => ['[X | Y, number]', '[X, number]'],
        'an optional tuple element\'s arm' => ['[string, (number | Y)?]', '[string, number?]'],
        'an optional tuple element left a union' => ['[(A | B | Y)?]', '[(A | B)?]'],
        'a rest element\'s arm' => ['[string, ...(X | Y)[]]', '[string, ...X[]]'],
        'an intersection part\'s parenthesized arm' => ['(X | Y) & Z', 'X & Z'],
        'a parenthesized union left two arms' => ['(A | B | Y) & Z', '(A | B) & Z'],
        'a readonly list\'s element' => ['readonly (X | Y)[]', 'readonly X[]'],
        'a deeply nested arm' => ['{ a: { b: [Record<string, (X | Y)[]>] } }', '{ a: { b: [Record<string, X[]>] } }'],
    ]);

    test('re-prints a list whose element union changed', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, static fn (string $arm): ?string => in_array($arm, ['Y', '$gap1'], true) ? null : $arm))
            ->toBe($expected);
    })->with([
        'one arm left' => ['(X | Y)[]', 'X[]'],
        'a list left' => ['(number[] | $gap1)[]', 'number[][]'],
        'two arms left' => ['(A | B | Y)[]', '(A | B)[]'],
        'an intersection left' => ['(A & B | Y)[]', '(A & B)[]'],
        'a list of a list' => ['(X | Y)[][]', 'X[][]'],
        'no arm left' => ['Y[]', 'unknown[]'],
    ]);

    test('reads a union left with no arm as unknown', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, droppingY()))->toBe($expected);
    })->with([
        'the whole type' => ['Y', 'unknown'],
        'the whole type, twice' => ['Y | Y', 'unknown'],
        'an object member' => ['{ a: Y; b: number }', '{ a: unknown; b: number }'],
        'a tuple element' => ['[Y, number]', '[unknown, number]'],
        'a generic argument' => ['Record<string, Y>', 'Record<string, unknown>'],
    ]);

    test('drops an arm a rewrite made alike an earlier one, and keeps arms spelled alike that it left', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, static fn (string $arm): ?string => $arm === '$gap1' ? null : $arm))->toBe($expected);
    })->with([
        'an object that loses its gap' => ['{ a: string | $gap1 } | { a: string }', '{ a: string }'],
        'a list that loses its gap' => ['(string | $gap1)[] | string[]', 'string[]'],
        'a later arm that loses its gap' => ['string[] | (string | $gap1)[]', 'string[]'],
        'two classes spelled alike' => ['User | User', 'User | User'],
        'two classes spelled alike beside a dropped arm' => ['User | User | $gap1', 'User | User'],
    ]);

    test('takes the text the callback returns for an arm', function () {
        expect(TypeRewriter::rewrite('{ a: X | Z }[] | X', static fn (string $arm): string => $arm === 'X' ? 'W' : $arm))
            ->toBe('{ a: W | Z }[] | W');
    });

    test('reads literal text and arrow types whole', function (string $type) {
        $visited = [];

        expect(TypeRewriter::rewrite($type, static function (string $arm) use (&$visited): ?string {
            $visited[] = $arm;

            return $arm === 'Y' ? null : $arm;
        }))->toBe($type)
            ->and($visited)->not->toContain('Y');
    })->with([
        'a string literal holding `| Y`' => ["'X | Y'"],
        'a template literal holding `| Y`' => ['`X | Y`'],
        'a template literal\'s placeholder' => ['`${X | Y}`'],
        'an arrow\'s return type' => ['() => X | Y'],
        'an arrow in a member' => ['{ fn: () => void | Y }'],
        'an arrow in a generic argument' => ['Foo<() => X | Y>'],
        'an indexed access' => ['T[Y]'],
    ]);
});

describe('members', function () {
    test('passes each object member at every depth to the callback with its rewritten value', function () {
        $visited = [];

        TypeRewriter::rewrite(
            '{ a: { b?: X | Y }; "c-d": Z }',
            droppingY(),
            static function (string $key, string $value) use (&$visited): array {
                $visited[] = [$key, $value];

                return [$key, $value];
            },
        );

        expect($visited)->toBe([['b?', 'X'], ['a', '{ b?: X }'], ['"c-d"', 'Z']]);
    });

    test('rewrites, renames or drops a member', function (string $type, Closure $member, string $expected) {
        expect(TypeRewriter::rewrite($type, member: $member))->toBe($expected);
    })->with([
        'a dropped member' => ['{ a: string; b: number }', static fn (string $key, string $value): ?array => $key === 'b' ? null : [$key, $value], '{ a: string }'],
        'every member dropped' => ['{ a: string }', static fn (string $key, string $value): ?array => null, '{}'],
        'a nested member dropped' => ['{ a: { b: string; c: number } }[]', static fn (string $key, string $value): ?array => $key === 'c' ? null : [$key, $value], '{ a: { b: string } }[]'],
        'a renamed key' => ['{ a: string }', static fn (string $key, string $value): array => [$key === 'a' ? 'b?' : $key, $value], '{ b?: string }'],
        'a new value' => ['{ a: string; b: number }', static fn (string $key, string $value): array => [$key, $key === 'a' ? 'User' : $value], '{ a: User; b: number }'],
        'comma separators kept' => ['Inertia.SharedData & { a: string, b: number, c: boolean }', static fn (string $key, string $value): ?array => $key === 'b' ? null : [$key, $value], 'Inertia.SharedData & { a: string, c: boolean }'],
        'a member of an Omit part' => ["Omit<{ by: string; at: number }, 'id'> & { id: number }", static fn (string $key, string $value): ?array => $key === 'at' ? null : [$key, $value], "Omit<{ by: string }, 'id'> & { id: number }"],
    ]);
});

describe('intersections', function () {
    test('drops a part the rewrite emptied while another part remains', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, member: static fn (string $key, string $value): ?array => $key === 'x' ? null : [$key, $value]))
            ->toBe($expected);
    })->with([
        'a last part' => ['A & B & { x: number }', 'A & B'],
        'a first part' => ['{ x: number } & A', 'A'],
        'a middle part' => ['A & { x: number } & B', 'A & B'],
        'a part in a group' => ['{ a: (A & { x: number }) | null }', '{ a: A | null }'],
        'a part in a list' => ['(A & B & { x: number })[]', '(A & B)[]'],
        'every part' => ['{ x: number } & { x: string }', '{}'],
        'a part left with a member' => ['A & { x: number; y: string }', 'A & { y: string }'],
    ]);

    test('keeps an empty object part the rewrite left alone', function () {
        expect(TypeRewriter::rewrite('A & {} | Y', droppingY()))->toBe('A & {}')
            ->and(TypeRewriter::rewrite('{} & A', droppingY()))->toBe('{} & A');
    });

    test('keeps the spacing around the parts left', function () {
        expect(TypeRewriter::rewrite('A&{ x: number }&B', member: static fn (string $key, string $value): ?array => null))
            ->toBe('A&B');
    });
});

describe('object text', function () {
    test('keeps the members a rewrite left alone, and the object\'s padding, as written', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, droppingY()))->toBe($expected);
    })->with([
        'a compact object' => ['{a:string; b: X | Y}', '{a:string; b: X}'],
        'unpadded commas' => ['Record<string, {title: string | Y, content: string}>', 'Record<string, {title: string, content: string}>'],
        'padding and a trailing separator' => ['{  a: X | Y ;  b:Y|Z ; }', '{  a: X ;  b:Z ; }'],
        'spacing around the colon' => ['{ a :X | Y }', '{ a :X }'],
    ]);

    test('drops a member with the separator after it, or before it where it is the last', function (string $type, string $expected) {
        expect(TypeRewriter::rewrite($type, member: static fn (string $key, string $value): ?array => $key === 'x' ? null : [$key, $value]))
            ->toBe($expected);
    })->with([
        'the last' => ['{title: string, x: number}', '{title: string}'],
        'the first' => ['{ x: number; a: string }', '{ a: string }'],
        'a middle one' => ['{a:string,x:number,b:string}', '{a:string,b:string}'],
        'the only one' => ['{x:number}', '{}'],
    ]);

    test('renames a key keeping the spacing around its colon', function () {
        expect(TypeRewriter::rewrite('{a :string}', member: static fn (string $key, string $value): array => ['b?', $value]))
            ->toBe('{b? :string}');
    });
});
