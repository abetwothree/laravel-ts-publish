<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape\DeclaredTypeMerge;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * The type merging a body's reading with a declared type, neither spelling a class.
 */
function mergedType(string $body, string $declared): string
{
    return new DeclaredTypeMerge([])->merge([$body, []], [$declared, []])[0];
}

// R7 rule 1.
test('a position the body reads as unknown takes the declared type, keeping the body\'s null', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'unknown' => ['unknown', 'string | null', 'string | null'],
    'unknown beside null' => ['unknown | null', 'number', 'number | null'],
    'mixed' => ['mixed', '{ a: number }', '{ a: number }'],
]);

test('tells how much a type says of its value, its null aside', function (string $type, int $tells) {
    expect(DeclaredTypeMerge::tells($type))->toBe($tells);
})->with([
    'unknown' => ['unknown', 0],
    'mixed' => ['mixed', 0],
    'unknown beside null' => ['unknown | null', 0],
    'a container of unknown' => ['unknown[] | null', 1],
    'a record of unknown' => ['Record<string, unknown>', 1],
    'a type beside unknown' => ['string | unknown', 2],
    'a type' => ['string', 2],
]);

// R7 rule 2.
test('a declared type that says nothing keeps the body\'s reading', function (string $declared) {
    expect(mergedType('{ a: string }', $declared))->toBe('{ a: string }');
})->with(['unknown', 'unknown | null', 'mixed']);

// R7 rule 3.
test('a shape merges member by member over the members the body sends', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a member left unknown' => ['{ a: unknown; b: string }', '{ a: number; b: string }', '{ a: number; b: string }'],
    'a member only the declaration has' => ['{ a: unknown }', '{ a: number; extra: string }', '{ a: number }'],
    'a member the declaration lacks' => ['{ a: unknown; t: string }', '{ a: number }', '{ a: number; t: string }'],
    'members declared in another order' => ['{ a: string; b: unknown }', '{ b: number; a: string }', '{ a: string; b: number }'],
    'the body\'s optional flags' => ['{ a?: unknown; b: unknown }', '{ a: number; b?: number }', '{ a?: number; b: number }'],
    'a nested shape' => ['{ inner: { a: unknown } }', '{ inner: { a: number; b: string } }', '{ inner: { a: number } }'],
]);

// R7 rule 4.
test('a list or a record merges its element or value, and another container kind keeps the body\'s', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a list element' => ['{ a: unknown }[]', '{ a: number }[]', '{ a: number }[]'],
    'a grouped element' => ['({ a: unknown } | null)[]', '({ a: number } | null)[]', '({ a: number } | null)[]'],
    'a record value' => ['Record<string, { n: unknown }>', 'Record<string, { n: number }>', 'Record<string, { n: number }>'],
    'each arm with the declared arm of its kind' => ['{ a: unknown } | unknown[]', '{ a: number } | number[]', '{ a: number } | number[]'],
    'a nullable list the declaration lists' => ['{ a: unknown }[] | null', '{ a: number }[]', '{ a: number }[] | null'],
]);

// R10: a body arm pairs with the declared arm of its kind whose keys match it best, its discriminant literal first.
test('a body shape merges with the declared shape whose keys match it best, and stays where none does', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'shapes told apart by their keys' => [
        '({ type: string; x: unknown } | { type: string; y: unknown })[]',
        "({ type: 'a'; x: number } | { type: 'b'; y: string })[]",
        "({ type: 'a'; x: number } | { type: 'b'; y: string })[]",
    ],
    'one shape of two declared' => ['{ type: string; x: unknown }', "{ type: 'a'; x: number } | { type: 'b'; y: string }", "{ type: 'a'; x: number }"],
    'shapes told apart by the discriminant' => ["{ type: 'b'; v: unknown }", "{ type: 'a'; v: number } | { type: 'b'; v: string }", "{ type: 'b'; v: string }"],
    'shapes that match it alike, each kept' => ['{ v: unknown }', '{ v: number; a: string } | { v: string; b: string }', '{ v: number } | { v: string }'],
    'shapes alike but for a widened discriminant' => [
        '{ type: string; value: unknown }',
        "{ type: 'text'; value: string } | { type: 'count'; value: number }",
        "{ type: 'text'; value: string } | { type: 'count'; value: number }",
    ],
]);

// R13: `unknown` tells less than a vague container, which tells less than any other type; the merge takes the more.
test('a reading takes the side that tells more of it: unknown, then a vague container, then any other type', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a vague list over unknown' => ['unknown', 'unknown[]', 'unknown[]'],
    'a vague record over unknown beside null' => ['unknown | null', 'Record<string, unknown>', 'Record<string, unknown> | null'],
    'a list over a vague record' => ['Record<string, unknown>', 'string[]', 'string[]'],
    'a list over a vague declaration' => ['string[]', 'unknown[]', 'string[]'],
    'a body\'s vague list over a declared vague record' => ['unknown[]', 'Record<string, unknown>', 'unknown[]'],
    'a vague list over a lone null' => ['null', 'unknown[]', 'unknown[] | null'],
    'a lone null over any other type' => ['null', 'string', 'null'],
]);

test('a lone null a union kept beside an arm no rule types takes the declared type beside it', function () {
    expect(mergedType('{ tags: null | $gap1; n: null }', '{ tags: number[]; n: number }'))->toBe('{ tags: number[] | null; n: null }')
        ->and(mergedType('{ tags: null | $gap1 }', '{ tags: unknown }'))->toBe('{ tags: null | $gap1 }')
        ->and(VisibleGaps::stripped(mergedType('{ tags: null | $gap1 }', '{ tags: unknown }'))[0])->toBe('{ tags: null }');
});

// R27: the arm a union left out beside a typed arm and its `null` reads as the declaration, beside the body's arms.
test('a typed arm and null a union kept beside an arm no rule types take the declared type beside them', function () {
    expect(mergedType('{ tags: number | null | $gap1 }', '{ tags: number[] }'))->toBe('{ tags: number | null | number[] }')
        ->and(mergedType('{ tags: number | null | $gap1 }', '{ tags: number }'))->toBe('{ tags: number | null }')
        ->and(mergedType('{ tags: number | null }', '{ tags: number[] }'))->toBe('{ tags: number | null }');
});

// D2: a gap stands for an arm the body cannot type, so each declared arm the body's other arms do not cover joins them,
// after them; a reading of only `null` and gaps takes the declared type whole, as an `unknown` beside `null` does.
test('a gap the body kept takes each declared arm its other arms do not cover, after them', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a list beside null' => ['number[] | null | $gap1', 'string[]', 'number[] | null | string[]'],
    'a shape beside null' => ['{ a: number } | null | $gap1', '{ a: number; b: string }', '{ a: number } | null | { a: number; b: string }'],
    'a scalar beside null' => ['number | null | $gap1', 'number[]', 'number | null | number[]'],
    'a gap before the null' => ['number | $gap1 | null', 'number[]', 'number | null | number[]'],
    'a lone null' => ['null | $gap1', 'number[]', 'number[] | null'],
    'only gaps' => ['$gap1 | $gap2', 'number[]', 'number[]'],
    'a literal a body arm covers' => ['string | $gap1', "'a' | number", 'string | number'],
    'an empty list' => ['never[] | $gap1', 'number[]', 'never[] | number[]'],
    'a declared null' => ['number | $gap1', 'number[] | null', 'number | number[] | null'],
    'a nested gap' => ['{ tags: (number | $gap1)[] }[]', '{ tags: (number | string)[] }[]', '{ tags: (number | string)[] }[]'],
    'an arrow beside the declared arm' => ['(() => string) | $gap1', 'string', '(() => string) | string'],
    'an arrow alone' => ['(() => string) | $gap1', '() => string', '() => string'],
]);

test('a gap stays beside the body\'s arms where the declared type tells nothing, for the strip to take out', function (string $declared) {
    expect(mergedType('number | null | $gap1', $declared))->toBe('number | null | $gap1');
})->with(['mixed', 'unknown', 'unknown | null']);

test('an unknown arm the body reads keeps taking only the declared arms no other body arm covers', function () {
    expect(mergedType('string | unknown', "'a' | number"))->toBe('string | number')
        ->and(mergedType('string | unknown', 'number | null'))->toBe('string | number | null');
});

// R28: a member pairs with the declared member of the key it names, however either spells it.
test('a member pairs with the declared member of the key it names, a numeric key printed as declared', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a quoted numeric key' => ['{ "0": unknown; by: string }', '{ 0: number; by: string }', '{ 0: number; by: string }'],
    'a single-quoted optional one' => ["{ '1'?: { a: unknown } }", '{ 1: { a: number } }', '{ 1?: { a: number } }'],
    'a quoted name' => ['{ "a": unknown }', '{ a: number }', '{ "a": number }'],
    'a key only the declaration has' => ['{ "0": number }', '{ 0: number; 1?: string }', '{ 0: number; 1?: string }'],
]);

// R28: a body shape pairs with the declared shape holding the keys it names, however either spells them.
test('pairs a body shape with a declared one by the key it decodes to', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a quoted numeric key' => ['{ "0": unknown }', '{ 0: number; z: string } | { 1: string }', '{ 0: number }'],
    'a quoted name' => ['{ "a": unknown }', '{ a: number; z: string } | { b: string }', '{ "a": number }'],
    'a single-quoted name' => ["{ 'a': unknown }", '{ b: string } | { a: number; z: string }', "{ 'a': number }"],
]);

// R29: an intersection of objects, as a spread beside keys prints, merges as the one object it sends.
test('an intersection of objects merges as the one object it sends', function () {
    $classes = new DeclaredTypeMerge(['User']);

    expect(mergedType("Omit<{ by: unknown; n: string }, 'n'> & { n: number }", '{ by: string; n: number; extra: string }'))
        ->toBe('{ by: string; n: number }')
        ->and(mergedType("(Omit<{ by: unknown; #\$open: unknown }, 'n'> & { n: number })[]", '{ by: string; n: number; extra: string }[]'))
        ->toBe('{ by: string; n: number; extra: string }[]')
        ->and(mergedType("(Omit<{ by: unknown }, 'n'> & { n: number })[]", '{ by: string; n: number; extra: string }[]'))
        ->toBe('{ by: string; n: number }[]')
        ->and(mergedType("{ n: unknown } & Omit<{ n: string }, 'n'>", '{ n: number }'))->toBe('{ n: number }')
        ->and(mergedType("Omit<Record<string, string>, 'by'> & { by: unknown }", '{ by: number }'))
        ->toBe("Omit<Record<string, string>, 'by'> & { by: unknown }")
        ->and($classes->merge(["Omit<{ by: User; n: User }, 'n'> & { n: unknown }", [CrmUser::class, User::class]], ['{ by: User; n: number }', [User::class]]))
        ->toBe(['{ by: User; n: number }', [CrmUser::class]]);
});

// R16: a shape under a declared list of shapes merges with its element shape, and else each member with the element.
test('a shape under a declared list merges with its element, keeping the shape', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a list of shapes' => ['{ a: unknown }', '{ a: number }[]', '{ a: number }'],
    'a list of scalars' => ['{ a: unknown; b: unknown }', 'number[]', '{ a: number; b: number }'],
    'a list of shapes beside null' => ['{ a: unknown }', '({ a: number } | null)[]', '{ a: number }'],
    'a list of two shapes' => ['{ a: unknown }', '({ a: number } | { b: string })[]', '{ a: number }'],
]);

// R21: a shape against declared containers of other kinds pairs with the best fit: a list holding a shape that shares
// a key with it, else a record, else any list.
test('a shape pairs with the declared container that fits it best', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a record or a list' => ['{ a: unknown }', 'Record<string, number> | number[]', '{ a: number }'],
    'a record or a list, a member typed' => ['{ a: unknown; b: number }', 'Record<string, number> | number[]', '{ a: number; b: number }'],
    'a record or a list of a matching shape' => ['{ a: unknown }', 'Record<string, string> | { a: number }[]', '{ a: number }'],
    'a list of a shape sharing no key, or a record' => ['{ a: unknown }', '{ z: number }[] | Record<string, string>', '{ a: string }'],
]);

// R11: a vague container takes the declaration whole; another container kind keeps the body's and merges its contents.
test('a vague container takes the declared one, and another kind merges its contents with the declared element or value', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a vague record under a list' => ['Record<string, unknown>', 'string[]', 'string[]'],
    'a vague list under a record' => ['unknown[]', 'Record<string, string>', 'Record<string, string>'],
    'a vague list under a shape' => ['unknown[]', '{ a: number }', '{ a: number }'],
    'a vague list beside null' => ['unknown[] | null', 'string[]', 'string[] | null'],
    'a list under a record' => ['{ by: unknown }[]', 'Record<string, { by: number }>', '{ by: number }[]'],
    'a shape under a record' => ['{ a: unknown; b: number }', 'Record<string, number>', '{ a: number; b: number }'],
    'a record under a list' => ['Record<string, { n: unknown }>', '{ n: number }[]', 'Record<string, { n: number }>'],
]);

// `filter()` re-lists an array keyed only by ints in the order it holds them, so a re-listed shape keyed 0 to n-1 that
// types no value takes each key's declared element in its key order (GH-154).
test('a re-listed shape keyed 0 to n-1 with every value unknown takes the declared tuple in its key order', function (string $body, string $declared, string $expected) {
    expect(new DeclaredTypeMerge([], relists: true)->merge([$body, []], [$declared, []])[0])->toBe($expected);
})->with([
    'keys in order' => ['{ "0": unknown; "1": unknown }', '[number, string]', '[number, string]'],
    'keys out of order' => ['{ "1": unknown; "0": unknown }', '[number, string]', '[string, number]'],
    'keys spelled bare' => ['{ 1: unknown; 0: unknown }', '[number, string]', '[string, number]'],
    'three keys' => ['{ "2": unknown; "0": unknown; "1": unknown }', '[number, string, boolean]', '[boolean, number, string]'],
    'the body\'s null' => ['{ "1": unknown; "0": unknown } | null', '[number, string]', '[string, number] | null'],
    'a declared null the body never sends' => ['{ "1": unknown; "0": unknown }', 'null | [number, string]', '[string, number]'],
    'an optional key last' => ['{ "1": unknown; "0"?: unknown }', '[number | null, string]', '[string, (number | null)?]'],
    'a declared optional element the body sends' => ['{ "1": unknown; "0": unknown }', '[number, string?]', '[string, number]'],
    'an optional key before a sent one' => ['{ "0"?: unknown; "1": unknown }', '[number, string]', '{ "0"?: unknown; "1": unknown }'],
    'two optional keys last' => ['{ "2": unknown; "0"?: unknown; "1"?: unknown }', '[number, string, boolean]', '{ "2": unknown; "0"?: unknown; "1"?: unknown }'],
    'a rest element' => ['{ "1": unknown; "0": unknown }', '[number, ...string[]]', '{ "1": unknown; "0": unknown }'],
    'a 3-tuple' => ['{ "1": unknown; "0": unknown }', '[number, string, boolean]', '{ "1": unknown; "0": unknown }'],
    'a declared shape' => ['{ "1": unknown; "0": unknown }', '{ 0: number; 1: string }', '{ 1: string; 0: number }'],
    'a declared list' => ['{ "1": unknown; "0": unknown }', 'number[]', '{ "1": number; "0": number }'],
    'a tuple beside another arm' => ['{ "1": unknown; "0": unknown }', '[number, string] | string', '{ "1": unknown; "0": unknown }'],
    'a typed value' => ['{ "1": string; "0": unknown }', '[number, string]', '{ "1": string; "0": unknown }'],
    'a key past n-1' => ['{ "2": unknown; "0": unknown }', '[number, string]', '{ "2": unknown; "0": unknown }'],
    'a named key' => ['{ a: unknown; "0": unknown }', '[number, string]', '{ a: unknown; "0": unknown }'],
    'a key with a leading zero' => ['{ "01": unknown; "0": unknown }', '[number, string]', '{ "01": unknown; "0": unknown }'],
]);

test('a shape keyed 0 to n-1 no filter re-lists keeps its keys under a declared tuple, as json_encode() sends them', function (string $body) {
    expect(mergedType($body, '[number, string]'))->toBe($body);
})->with(['{ "1": unknown; "0": unknown }', '{ "0": unknown; "1": unknown }', '{ "1": unknown; "0": unknown } | null']);

// A value a union left only gaps or `null` beside them holds what no rule typed, as an `unknown` one does (GH-154).
test('a re-listed shape whose values hold only gaps beside unknown or null takes the declared tuple in its key order', function () {
    $relisted = new DeclaredTypeMerge([], relists: true);

    expect($relisted->merge(['{ "1": null | $gap1; "0": unknown }', []], ['[number, string | null]', []])[0])->toBe('[string | null, number]')
        ->and($relisted->merge(['{ "1": $gap1 | $gap2; "0": unknown }', []], ['[number, string]', []])[0])->toBe('[string, number]')
        ->and($relisted->merge(['{ "1": unknown; "0": unknown; #$open: unknown }', []], ['[number, string]', []])[0])->toBe('[string, number]')
        ->and($relisted->merge(['{ "1": null; "0": unknown }', []], ['[number, string | null]', []])[0])->toBe('{ "1": null; "0": unknown }')
        ->and($relisted->merge(['{ "1": string | $gap1; "0": unknown }', []], ['[number, string]', []])[0])->toBe('{ "1": string | $gap1; "0": unknown }')
        ->and(mergedType('{ "1": null | $gap1; "0": unknown }', '[number, string | null]'))->toBe('{ "1": null | $gap1; "0": unknown }');
});

test('a re-listed tuple keeps each declared element\'s class beside it in its key order', function () {
    $merge = new DeclaredTypeMerge(['User', 'Post'], relists: true);

    expect($merge->merge(['{ "1": unknown; "0": unknown }', []], ['[User, Post]', [User::class, Post::class]]))
        ->toBe(['[Post, User]', [Post::class, User::class]])
        ->and($merge->merge(['{ "1": unknown; "0": unknown } | null', []], ['[User | null, Post]', [CrmUser::class, Post::class]]))
        ->toBe(['[Post, User | null] | null', [Post::class, CrmUser::class]]);
});

// R12, R15 and R19: a member only the declaration has is kept where it may be left out, and dropped where it must be
// sent from a shape whose every key the body names; a shape the open marker says may hold more keys keeps it.
test('a member only the declaration has stays where it is optional or the body\'s shape holds the open marker', function () {
    expect(mergedType('{ a: string }', '{ a: string; b: number; c?: string }'))->toBe('{ a: string; c?: string }')
        ->and(mergedType('{ a: string; #$open: unknown }', '{ a: string; b: number; c?: string }'))->toBe('{ a: string; b: number; c?: string }')
        ->and(mergedType('{ inner: { a: number; #$open: unknown }; other: { a: number } }', '{ inner: { a: number; b: string }; other: { a: number; b: string } }'))
        ->toBe('{ inner: { a: number; b: string }; other: { a: number } }')
        ->and(mergedType('{ a: string; #$open: unknown }[]', '{ a: string; b: number }[]'))->toBe('{ a: string; b: number }[]')
        ->and(mergedType('({ a: string; #$open: unknown } | { c: string })[]', '({ a: string; b: number } | { c: string; b: number })[]'))
        ->toBe('({ a: string; b: number } | { c: string })[]')
        ->and(mergedType('{ a: string; #$open: unknown }', 'Record<string, number>'))->toBe('{ a: string }');
});

// D2: a closed arm beside an open one with the same keys stays apart from it, each merged as it reads.
test('a closed shape beside an open one of the same keys merges apart from it', function () {
    expect(mergedType('({ by: string } | { by: string; #$open: unknown })[]', '{ by: string; extra: number }[]'))
        ->toBe('({ by: string } | { by: string; extra: number })[]')
        ->and(mergedType('{ by: string }[] | { by: string; #$open: unknown }[]', '{ by: string; extra: number }[]'))
        ->toBe('{ by: string }[] | { by: string; extra: number }[]');
});

// R20: a declared member a body index signature's pattern covers merges with that signature's value, which widens to
// admit it, so TypeScript finds every member assignable to the signature (TS2411).
test('a declared member an index signature covers merges with its value, which widens to admit it', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a stale member' => [
        '{ by: string; [key: `${string}_tag`]: number | undefined }',
        '{ by: string; x_tag: string }',
        '{ by: string; [key: `${string}_tag`]: number | undefined; x_tag: number | undefined }',
    ],
    'a member refining the value' => ['{ [key: string]: string }', "{ a: 'x' }", "{ [key: string]: string; a: 'x' }"],
    'a member the value widens for' => ['{ [key: string]: string }', "{ a: 'x' | null }", "{ [key: string]: string | null; a: 'x' | null }"],
    'a member two patterns cover' => [
        '{ [key: `x_${string}`]: number | undefined; [key: `${string}_y`]: string | undefined }',
        '{ x_y: string }',
        '{ [key: `x_${string}`]: number | undefined | string; [key: `${string}_y`]: string | undefined | number; x_y: number | undefined | string }',
    ],
    'a member no pattern covers' => [
        '{ by: string; [key: `${string}_tag`]: number | undefined }',
        '{ by: string; extra: string }',
        '{ by: string; [key: `${string}_tag`]: number | undefined }',
    ],
]);

// R7 rule 5, first bullet.
test('a declared type within the body\'s reading refines it', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'literals of a scalar' => ['string', "'a' | 'b'", "'a' | 'b'"],
    'literals of a nullable scalar' => ['string | null', "'a' | 'b' | null", "'a' | 'b' | null"],
    'a literal of a nullable number' => ['number | null', '1 | null', '1 | null'],
    'a boolean literal' => ['boolean', 'true', 'true'],
    'the same arms in another order' => ['string | null', 'null | string', 'string | null'],
]);

// R9 and R9a: each body arm the declaration refines takes its refinement, and each it leaves out stays, so a
// declaration never stops admitting what the body sends.
test('a declared type refines the body\'s arms one by one, keeping each it leaves out', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'the body\'s null' => ['string | null', 'string', 'string | null'],
    'literals of a scalar the body reads beside null' => ['string | null', "'a' | 'b'", "'a' | 'b' | null"],
    'one of the body\'s scalars' => ['string | number', "'a'", "'a' | number"],
    'a literal beside null of another scalar' => ['string | number', "'a' | null", "'a' | number | null"],
]);

// R7 rule 5, second bullet.
test('a body reading within the declared type stays', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a scalar declared beside null' => ['string', 'string | null', 'string'],
    'a literal of the declared scalar' => ["'a'", 'string', "'a'"],
    'a shape declared beside null' => ['{ a: string }', '{ a: string } | null', '{ a: string }'],
]);

// R7 rule 5, third bullet.
test('a body arm left unknown takes the declared arms no other body arm covers, and other arms keep the body\'s', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'an unknown arm' => ['string | unknown', "'a' | number", 'string | number'],
    'a declared null no body arm covers' => ['string | unknown', 'number | null', 'string | number | null'],
    'another scalar' => ['string', 'number', 'string'],
    'another scalar\'s literal' => ['number', "'1'", 'number'],
    'a literal the body reads' => ["'x'", "'y'", "'x'"],
    'mixed literals' => ['string', "'a' | 1", 'string'],
]);

// R7's cells N5 and N7: a null the declaration adds admits more than the body sends, never less.
test('a declared type whose arms but null refine the body\'s reading is taken whole', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'a string literal' => ['string', "'a' | null", "'a' | null"],
    'a numeric literal' => ['number', '1 | null', '1 | null'],
    'a lone null' => ['string', 'null', 'string'],
]);

// R7 rule 6.
test('classes compare by the class behind each token, never by spelling', function () {
    $merge = new DeclaredTypeMerge(['User', 'Post']);

    expect($merge->merge(['User', [CrmUser::class]], ['User | null', [User::class]]))->toBe(['User', [CrmUser::class]])
        ->and($merge->merge(['User | Post', [User::class, Post::class]], ['User', [User::class]]))->toBe(['User | Post', [User::class, Post::class]])
        ->and($merge->merge(['User', [User::class]], ['Post', [Post::class]]))->toBe(['User', [User::class]]);
});

test('a filled position keeps the declared class beside each class the body reads by the same name', function (string $declared, array $slots) {
    $body = ['{ by: User; ed: User; other: unknown }', [CrmUser::class, User::class]];

    expect(new DeclaredTypeMerge(['User'])->merge($body, [$declared, $slots]))
        ->toBe(['{ by: User; ed: User; other: User | null }', [CrmUser::class, User::class, User::class]]);
})->with([
    'the body\'s classes declared' => ['{ by: User; ed: User; other: User | null }', [CrmUser::class, User::class, User::class]],
    'members declared in another order' => ['{ other: User | null; by: User; ed: User }', [User::class, CrmUser::class, User::class]],
    'other classes declared where the body reads its own' => ['{ by: User; ed: User; other: User | null }', [User::class, CrmUser::class, User::class]],
]);

// R7 rule 7.
test('a literal is within its scalar, null only within null, and a wrapped enum is never its enum', function () {
    $wraps = new DeclaredTypeMerge(['StatusType'], [Status::class]);
    $plain = new DeclaredTypeMerge(['StatusType']);

    expect(DeclaredTypeMerge::armWithin("'a'", 'string'))->toBeTrue()
        ->and(DeclaredTypeMerge::armWithin('"a"', 'string'))->toBeTrue()
        ->and(DeclaredTypeMerge::armWithin('-1.5', 'number'))->toBeTrue()
        ->and(DeclaredTypeMerge::armWithin('false', 'boolean'))->toBeTrue()
        ->and(DeclaredTypeMerge::armWithin('string', "'a'"))->toBeFalse()
        ->and(DeclaredTypeMerge::armWithin("'1'", 'number'))->toBeFalse()
        ->and(DeclaredTypeMerge::armWithin('null', 'null'))->toBeTrue()
        ->and(DeclaredTypeMerge::armWithin('null', 'string'))->toBeFalse()
        ->and(DeclaredTypeMerge::armWithin('string', 'unknown'))->toBeFalse()
        ->and(mergedType('null', 'string'))->toBe('null')
        ->and(mergedType('string', 'null'))->toBe('string')
        ->and($plain->merge(['StatusType | string', [Status::class]], ['StatusType | \'a\'', [Status::class]]))->toBe(['StatusType | \'a\'', [Status::class]])
        ->and($wraps->merge(['StatusType | string', [Status::class]], ['StatusType | \'a\'', [Status::class]]))->toBe(['StatusType | string', [Status::class]]);
});

test('a wrapped enum beside a list keeps the body\'s arm and merges the list', function () {
    $merge = new DeclaredTypeMerge(['StatusType', 'User'], [Status::class]);
    $body = ['StatusType | { by: User; other: unknown }[]', [Status::class, CrmUser::class]];
    $declared = ['StatusType | { other: User | null; by: User }[]', [Status::class, User::class, CrmUser::class]];

    expect($merge->merge($body, $declared))
        ->toBe(['StatusType | { by: User; other: User | null }[]', [Status::class, CrmUser::class, User::class]]);
});

/**
 * Seeded body and declared types for the merge's properties: each body one to three typed arms with one or two gap
 * arms placed among them, and each declared type one to three arms, none of which tells only its container.
 *
 * @return list<array{string, string}>
 */
function gapMergeCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $bodyArms = ['number', 'string', 'boolean', 'null', "'a'", '1', 'number[]', 'string[]', '{ a: number }', '{ a: string; b: number }', 'Record<string, number>', '(number | null)[]', '{ a: number }[]'];
    $declaredArms = ['number', 'string', 'boolean', 'null', "'a'", "'b'", '1', 'number[]', 'string[]', '{ a: number }', '{ a: number; b: string }', 'Record<string, string>', '{ a: number }[]', '(string | null)[]'];
    $made = [];

    foreach (range(1, $cases) as $case) {
        $body = array_slice($random->shuffleArray($bodyArms), 0, $random->getInt(1, 3));

        foreach (range(1, $random->getInt(1, 2)) as $gap) {
            array_splice($body, $random->getInt(0, count($body)), 0, [VisibleGaps::arm($gap)]);
        }

        $made[] = [implode(' | ', $body), implode(' | ', array_slice($random->shuffleArray($declaredArms), 0, $random->getInt(1, 3)))];
    }

    return $made;
}

/**
 * The kind of container one arm is, as the merge pairs arms, or null for an arm compared whole.
 */
function mergedArmKind(string $arm): ?string
{
    return match (true) {
        str_starts_with($arm, '{') => 'object',
        str_starts_with($arm, 'Record<') => 'record',
        str_ends_with($arm, '[]') => 'list',
        default => null,
    };
}

test('a merge keeps each arm the body proves is sent and every declared arm beside the gaps they leave', function (string $body, string $declared) {
    $merged = TsTypeString::splitTopLevelUnion(mergedType($body, $declared));
    $declaredArms = TsTypeString::splitTopLevelUnion($declared);
    $covers = fn (string $arm): bool => in_array($arm, $merged, true)
        || array_any($merged, fn (string $other): bool => DeclaredTypeMerge::armWithin($arm, $other));

    foreach (VisibleGaps::aside($body)[0] === 'null' ? [] : TsTypeString::splitTopLevelUnion(VisibleGaps::aside($body)[0]) as $arm) {
        $kind = mergedArmKind($arm);
        $refined = array_any($declaredArms, fn (string $other): bool => in_array($other, $merged, true) && DeclaredTypeMerge::armWithin($other, $arm));

        expect($kind === null ? in_array($arm, $merged, true) || $refined : array_any($merged, fn (string $other): bool => mergedArmKind($other) === $kind))
            ->toBeTrue("{$arm} of {$body} under {$declared}");
    }

    expect(array_all($declaredArms, $covers))->toBeTrue("{$declared} beside {$body}")
        ->and(VisibleGaps::ids(implode(' | ', $merged)))->toBe([]);
})->with(fn (): array => gapMergeCases(154, 300));

test('a gap the body kept stays where the declared type tells nothing', function (string $body) {
    expect(mergedType($body, 'mixed'))->toBe($body)
        ->and(mergedType($body, 'unknown | null'))->toBe($body);
})->with(fn (): array => array_column(gapMergeCases(155, 50), 0));

test('a body with no gap merges as it did before gaps were kept', function (string $body, string $declared, bool $relists, string $merged) {
    expect(new DeclaredTypeMerge([], relists: $relists)->merge([$body, []], [$declared, []])[0])->toBe($merged);
})->with(array_map(
    static fn (array $case): array => [$case['body'], $case['declared'], $case['relists'], $case['merged']],
    (array) json_decode((string) file_get_contents(__DIR__.'/Fixtures/declared-type-merge-baseline.json'), true),
));

test('a merge reads the same each time, whatever merge ran before it', function () {
    $cases = gapMergeCases(156, 100);
    $first = array_map(fn (array $case): string => mergedType(...$case), $cases);
    $shared = new DeclaredTypeMerge([]);
    $again = array_map(fn (array $case): string => $shared->merge([$case[0], []], [$case[1], []])[0], array_reverse($cases));

    expect(array_reverse($again))->toBe($first);
});
