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
    'a list the declaration types as a shape' => ['unknown[]', '{ a: number }', 'unknown[]'],
    'a shape the declaration types as a list' => ['{ a: unknown }', '{ a: number }[]', '{ a: unknown }'],
    'a record the declaration types as a list' => ['Record<string, unknown>', 'number[]', 'Record<string, unknown>'],
    'each arm with the declared arm of its kind' => ['{ a: unknown } | unknown[]', '{ a: number } | number[]', '{ a: number } | number[]'],
    'a nullable list the declaration lists' => ['{ a: unknown }[] | null', '{ a: number }[]', '{ a: number }[] | null'],
]);

// R7 rule 5, first bullet.
test('a declared type within the body\'s reading refines it', function (string $body, string $declared, string $expected) {
    expect(mergedType($body, $declared))->toBe($expected);
})->with([
    'literals of a scalar' => ['string', "'a' | 'b'", "'a' | 'b'"],
    'literals of a nullable scalar' => ['string | null', "'a' | 'b' | null", "'a' | 'b' | null"],
    'a literal of a nullable number' => ['number | null', '1 | null', '1 | null'],
    'a boolean literal' => ['boolean', 'true', 'true'],
    'literals of a scalar the body reads beside null' => ['string | null', "'a' | 'b'", "'a' | 'b'"],
    'the same arms in another order' => ['string | null', 'null | string', 'null | string'],
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
        ->and($merge->merge(['User | null', [User::class]], ['User', [User::class]]))->toBe(['User', [User::class]])
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
        ->and($plain->merge(['StatusType | null', [Status::class]], ['StatusType', [Status::class]]))->toBe(['StatusType', [Status::class]])
        ->and($wraps->merge(['StatusType | null', [Status::class]], ['StatusType', [Status::class]]))->toBe(['StatusType | null', [Status::class]]);
});

test('a wrapped enum beside a list keeps the body\'s arm and merges the list', function () {
    $merge = new DeclaredTypeMerge(['StatusType', 'User'], [Status::class]);
    $body = ['StatusType | { by: User; other: unknown }[]', [Status::class, CrmUser::class]];
    $declared = ['StatusType | { other: User | null; by: User }[]', [Status::class, User::class, CrmUser::class]];

    expect($merge->merge($body, $declared))
        ->toBe(['StatusType | { by: User; other: User | null }[]', [Status::class, CrmUser::class, User::class]]);
});
