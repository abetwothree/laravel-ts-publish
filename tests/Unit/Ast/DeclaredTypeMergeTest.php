<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Composition\DeclaredTypeMerge;
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
    $merge = new DeclaredTypeMerge([], [], [".tags\0null"]);

    expect($merge->merge(['{ tags: null; n: null }', []], ['{ tags: number[]; n: number }', []])[0])->toBe('{ tags: number[] | null; n: null }')
        ->and($merge->merge(['{ tags: null }', []], ['{ tags: unknown }', []])[0])->toBe('{ tags: null }');
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

// R12, R15 and R19: a member only the declaration has is kept where it may be left out, and dropped where it must be
// sent from a shape whose every key the body names; a shape open at its path keeps it as declared.
test('a member only the declaration has stays where it is optional or the body\'s shape is open at its path', function () {
    $open = new DeclaredTypeMerge([], [], ["\0{ a: string }"]);
    $inner = new DeclaredTypeMerge([], [], [".inner\0{ a: number }"]);
    $elements = new DeclaredTypeMerge([], [], ["[]\0{ a: string }", "[]\0{ b: string }"]);
    $shapes = ['{ inner: { a: number }; other: { a: number } }', []];

    expect(mergedType('{ a: string }', '{ a: string; b: number; c?: string }'))->toBe('{ a: string; c?: string }')
        ->and($open->merge(['{ a: string }', []], ['{ a: string; b: number; c?: string }', []])[0])->toBe('{ a: string; b: number; c?: string }')
        ->and($inner->merge($shapes, ['{ inner: { a: number; b: string }; other: { a: number; b: string } }', []])[0])
        ->toBe('{ inner: { a: number; b: string }; other: { a: number } }')
        ->and($elements->merge(['{ a: string }[]', []], ['{ a: string; b: number }[]', []])[0])->toBe('{ a: string; b: number }[]')
        ->and($elements->merge(['({ a: string } | { c: string })[]', []], ['({ a: string; b: number } | { c: string; b: number })[]', []])[0])
        ->toBe('({ a: string; b: number } | { c: string })[]');
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
