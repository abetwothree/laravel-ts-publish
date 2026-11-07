<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnShapeFixture;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\RecipientSettingsResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\ValueObjects\MailRecipient;
use Workbench\App\ValueObjects\RecipientList;

/**
 * @param  list<array{name: string, type: string, optional?: bool}>  $properties
 */
function refinedAnalysis(array $properties, string $method, bool $keepsUnresolvedNames = true): MethodAnalysis
{
    $analysis = new MethodAnalysis(properties: array_map(
        fn (array $p): array => [
            'name' => $p['name'],
            'type' => $p['type'],
            'optional' => $p['optional'] ?? false,
            'description' => '',
        ],
        $properties,
    ));

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, $method), $keepsUnresolvedNames);

    return $analysis;
}

test('a property the body already typed is never overwritten by the shape', function () {
    $props = collect(refinedAnalysis([['name' => 'known', 'type' => 'number']], 'shaped')->properties)->keyBy('name');

    expect($props['known']['type'])->toBe('number');
});

test('a shape value naming a bare class is not applied', function () {
    $props = collect(refinedAnalysis([['name' => 'handle', 'type' => 'unknown']], 'shaped')->properties)->keyBy('name');

    expect($props['handle']['type'])->toBe('unknown');
});

test('a shape value naming no PHP type at all keeps the name the app declares', function () {
    $props = collect(refinedAnalysis([['name' => 'custom', 'type' => 'unknown']], 'shaped')->properties)->keyBy('name');

    expect($props['custom'])->toMatchArray(['type' => 'CustomThing', 'optional' => true]);
});

test('a name no class answers to is not kept from the analyzed method\'s own docblock', function () {
    $props = collect(refinedAnalysis([['name' => 'custom', 'type' => 'unknown']], 'shaped', keepsUnresolvedNames: false)->properties)->keyBy('name');

    expect($props['custom'])->toMatchArray(['type' => 'unknown', 'optional' => true]);
});

test('a bare name a class answers to is not kept, since the shape cannot import it', function () {
    $props = collect(refinedAnalysis([['name' => 'text', 'type' => 'unknown']], 'spelled')->properties)->keyBy('name');

    expect($props['text']['type'])->toBe('unknown');
});

test('a shape value lists each arm once, and one left unknown fills nothing', function (string $name, string $expected) {
    $props = collect(refinedAnalysis([['name' => $name, 'type' => 'unknown']], 'spelled')->properties)->keyBy('name');

    expect($props[$name]['type'])->toBe($expected);
})->with([
    'int|float' => ['amount', 'number'],
    'two unknown arms' => ['spare', 'unknown'],
    'string literals' => ['mode', "'draft' | 'live'"],
]);

test('a shape value naming an overridden class fills with the override and brings its import', function () {
    $analysis = refinedAnalysis([
        ['name' => 'chart', 'type' => 'unknown'],
        ['name' => 'nested', 'type' => 'unknown'],
        ['name' => 'owner', 'type' => 'unknown'],
    ], 'overridden');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'chart' => 'ChartType',
        'nested' => '{ panel: UiNode; n: number }',
        'owner' => 'User',
    ])->and($analysis->customImports)->toBe([
        '@js/types/ui' => ['UiNode'],
        ComposedClassTypes::marker(Chart::class) => ['ChartType'],
    ]);
});

test('a filled key queues the model its member names, as a cast naming it does', function () {
    $analysis = refinedAnalysis([['name' => 'owner', 'type' => 'unknown']], 'overridden');

    expect($analysis->inlineModelFqcns)->toBe(['owner' => [User::class]])
        ->and($analysis->declaredTokenFqcns)->toBe(['owner' => [User::class]]);
});

test('a fill keeps a key whose value may be left out so, and a fill naming no class declares no slots', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'owner', 'type' => 'unknown', 'optional' => true, 'description' => ''],
        ['name' => 'chart', 'type' => 'unknown', 'optional' => true, 'description' => ''],
    ]);
    $analysis->leftOutValues = ['owner' => true, 'chart' => true];

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'overridden'));

    expect($analysis->leftOutValues)->toEqual(['owner' => true, 'chart' => true])
        ->and($analysis->declaredTokenFqcns)->toBe(['owner' => [User::class]]);
});

test('an override spelled like the model beside it keeps both arms, each token with its own class', function () {
    $analysis = refinedAnalysis([['name' => 'either', 'type' => 'unknown']], 'spelledLikeModel');

    expect($analysis->properties[0]['type'])->toBe('User | User')
        ->and($analysis->declaredTokenFqcns)->toBe(['either' => [null, User::class]])
        ->and($analysis->inlineModelFqcns)->toBe(['either' => [User::class]])
        ->and($analysis->customImports)->toBe(['@js/types/ui' => ['User']]);
});

test('a vague-only key takes its member in place of the vague arms, keeping the null arm', function (string $name, string $inferred, string $expected) {
    $props = collect(refinedAnalysis([['name' => $name, 'type' => $inferred]], 'vague')->properties)->keyBy('name');

    expect($props[$name]['type'])->toBe($expected);
})->with([
    'a nullable list' => ['list', 'unknown[] | null', 'string[] | null'],
    'a list' => ['list', 'unknown[]', 'string[]'],
    'an empty list' => ['list', 'never[]', 'string[]'],
    'a null before the list' => ['list', 'null | unknown[]', 'string[] | null'],
    'unknown with a null arm' => ['list', 'unknown | null', 'string[] | null'],
    'a record' => ['record', 'Record<string, unknown>', 'Record<string, number>'],
    'a nullable record' => ['record', 'Record<string, unknown> | null', 'Record<string, number> | null'],
]);

test('a vague member fills only an exact unknown, never a vague key it would make less specific', function (string $inferred, string $expected) {
    $props = collect(refinedAnalysis([['name' => 'vague', 'type' => $inferred]], 'vague')->properties)->keyBy('name');

    expect($props['vague']['type'])->toBe($expected);
})->with([
    'a list' => ['unknown[]', 'unknown[]'],
    'a nullable record' => ['Record<string, unknown> | null', 'Record<string, unknown> | null'],
    'unknown with a null arm' => ['unknown | null', 'unknown | null'],
    'unknown' => ['unknown', 'unknown'],
]);

test('a precise inferred key keeps its type over the docblock', function (string $inferred) {
    $props = collect(refinedAnalysis([['name' => 'precise', 'type' => $inferred]], 'vague')->properties)->keyBy('name');

    expect($props['precise']['type'])->toBe($inferred);
})->with([
    'a string' => ['string'],
    'a list of strings' => ['string[]'],
    'a vague arm beside a precise one' => ['unknown[] | string'],
    'a lone null' => ['null'],
    'an unknown inside a shape' => ['{ a: unknown }'],
]);

test('a union of two Records gives no record value', function () {
    $analysis = refinedAnalysis([['name' => 'a', 'type' => 'unknown']], 'records');

    expect($analysis->properties[0]['type'])->toBe('unknown')
        ->and($analysis->customImports)->toBe([]);
});

test('an array<string, V> return naming an overridden class fills with the override and brings its import', function () {
    $analysis = refinedAnalysis([
        ['name' => 'a', 'type' => 'unknown'],
        ['name' => '[key: `${string}_tag`]', 'type' => 'unknown | undefined'],
    ], 'overriddenRecord');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe([
        'a' => 'UiNode',
        '[key: `${string}_tag`]' => 'UiNode | undefined',
    ])->and($analysis->customImports)->toBe(['@js/types/ui' => ['UiNode']]);
});

test('a key the shape declares optional becomes optional, keeping its own type', function () {
    $props = collect(refinedAnalysis([['name' => 'maybe', 'type' => 'unknown']], 'shaped')->properties)->keyBy('name');

    expect($props['maybe'])->toMatchArray(['type' => 'number', 'optional' => true]);
});

test('an array<string, V> return fills every unknown property with V', function () {
    $props = collect(refinedAnalysis([
        ['name' => 'a', 'type' => 'unknown'],
        ['name' => 'b', 'type' => 'boolean'],
    ], 'record')->properties)->keyBy('name');

    expect($props['a']['type'])->toBe('string')
        ->and($props['b']['type'])->toBe('boolean');
});

test('an array<string, V> return fills an untyped index signature with V, keeping its | undefined', function () {
    $props = collect(refinedAnalysis([
        ['name' => '[key: `${string}_tag`]', 'type' => 'unknown | undefined'],
        ['name' => 'named', 'type' => 'unknown | undefined'],
    ], 'record')->properties)->keyBy('name');

    expect($props['[key: `${string}_tag`]'])->toMatchArray(['type' => 'string | undefined', 'optional' => false])
        ->and($props['named']['type'])->toBe('unknown | undefined');
});

test('an index signature with any other value, or under an array{...} shape, is left alone', function () {
    $merged = collect(refinedAnalysis([['name' => '[key: `${string}_tag`]', 'type' => 'string | undefined | unknown']], 'record')->properties)->keyBy('name');
    $shaped = collect(refinedAnalysis([['name' => '[key: `${string}_tag`]', 'type' => 'unknown | undefined']], 'shaped')->properties)->keyBy('name');

    expect($merged['[key: `${string}_tag`]']['type'])->toBe('string | undefined | unknown')
        ->and($shaped['[key: `${string}_tag`]'])->toMatchArray(['type' => 'unknown | undefined', 'optional' => false]);
});

test('a method with no return docblock leaves every property alone', function () {
    $props = collect(refinedAnalysis([['name' => 'a', 'type' => 'unknown']], 'undocumented')->properties)->keyBy('name');

    expect($props['a'])->toMatchArray(['type' => 'unknown', 'optional' => false]);
});

test('an already-optional property stays optional when the shape does not say so', function () {
    $props = collect(refinedAnalysis([['name' => 'known', 'type' => 'unknown', 'optional' => true]], 'shaped')->properties)->keyBy('name');

    expect($props['known'])->toMatchArray(['type' => 'string', 'optional' => true]);
});

// Where the body types a key but leaves a member, an element or a record value unknown, the docblock's member at that
// position fills it; every position the body types keeps the body's type, as the whole key does (GH-154).
test('a key the body types fills each unknown position inside it from the docblock', function (string $method, string $name, string $inferred, string $expected) {
    $analysis = refinedAnalysis([['name' => $name, 'type' => $inferred]], $method);

    expect($analysis->properties[0]['type'])->toBe($expected)
        ->and($analysis->declaredTokenFqcns)->toBe([]);
})->with([
    'list elements\' members' => ['nestedLists', 'rows', '{ name: unknown; email: unknown }[]', '{ name: string | null; email: string | null }[]'],
    'one member the body types' => ['nestedLists', 'rows', '{ name: string; email: unknown }[]', '{ name: string; email: string | null }[]'],
    'an optional member' => ['nestedLists', 'rows', '{ name?: unknown; email: string }[]', '{ name?: string | null; email: string }[]'],
    'a member the docblock lacks' => ['nestedLists', 'rows', '{ name: unknown; extra: unknown }[]', '{ name: string | null; extra: unknown }[]'],
    'a nullable list' => ['nestedLists', 'rows', '{ name: unknown; email: string }[] | null', '{ name: string | null; email: string }[] | null'],
    'a grouped element' => ['nestedLists', 'rows', '({ name: unknown; email: string } | null)[]', '({ name: string | null; email: string } | null)[]'],
    'a record value' => ['nestedLists', 'byKey', 'Record<string, { n: unknown }>', 'Record<string, { n: number }>'],
    'a nested shape' => ['nestedShapes', 'nested', '{ inner: { a: unknown; b: number } }', '{ inner: { a: number; b: number } }'],
    'a nullable shape' => ['nestedArms', 'maybe', '{ a: unknown } | null', '{ a: number } | null'],
    'an unknown list element' => ['nestedLists', 'rows', 'unknown[] | string', '{ name: string | null; email: string | null }[] | string'],
    'the arm of the docblock\'s kind' => ['nestedArms', 'either', '{ a: unknown } | unknown[]', '{ a: number } | number[]'],
]);

test('an unknown position whose docblock member is another kind of value stays unknown', function (string $method, string $name, string $inferred, string $expected) {
    $analysis = refinedAnalysis([['name' => $name, 'type' => $inferred]], $method);

    expect($analysis->properties[0]['type'])->toBe($expected)
        ->and($analysis->customImports)->toBe([])
        ->and($analysis->inlineModelFqcns)->toBe([]);
})->with([
    'a shape where the docblock lists' => ['nestedLists', 'rows', '{ name: unknown }', '{ name: unknown }'],
    'a list where the docblock has a shape' => ['nestedShapes', 'nested', 'string | unknown[]', 'string | unknown[]'],
    'a shape where the docblock has a scalar' => ['nestedShapes', 'scalar', '{ a: unknown }', '{ a: unknown }'],
    'a key the docblock lacks' => ['nestedLists', 'missing', '{ a: unknown }[]', '{ a: unknown }[]'],
]);

// A key with no channel of its own takes the fill's classes as a whole key's fill does; beside a channel the body
// records, whose slots no fill can line up with, a position naming a class stays unknown.
test('an unknown position whose member names a class fills with its import where the key has no channel', function () {
    $analysis = refinedAnalysis([['name' => 'owned', 'type' => '{ owner: unknown; n: unknown }[]']], 'nestedClasses');
    $overridden = refinedAnalysis([['name' => 'nested', 'type' => '{ panel: unknown; n: string }']], 'overridden');
    $reordered = refinedAnalysis([['name' => 'pair', 'type' => '{ second: unknown; first: unknown }']], 'nestedClasses');

    expect($analysis->properties[0]['type'])->toBe('{ owner: User; n: number }[]')
        ->and($analysis->declaredTokenFqcns)->toBe(['owned' => [User::class]])
        ->and($analysis->inlineModelFqcns)->toBe(['owned' => [User::class]])
        ->and($reordered->properties[0]['type'])->toBe('{ second: Post | null; first: User }')
        ->and($reordered->declaredTokenFqcns)->toBe(['pair' => [Post::class, User::class]])
        ->and($overridden->properties[0]['type'])->toBe('{ panel: UiNode; n: string }')
        ->and($overridden->customImports)->toBe(['@js/types/ui' => ['UiNode']])
        ->and($overridden->declaredTokenFqcns)->toBe([]);
});

test('an unknown position whose member names a class stays unknown beside a channel the body records', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'owned', 'type' => '{ owner: unknown; n: unknown; post: Post }[]', 'optional' => false, 'description' => ''],
    ]);
    $analysis->inlineModelFqcns = ['owned' => [Post::class]];

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'nestedClasses'));

    expect($analysis->properties[0]['type'])->toBe('{ owner: unknown; n: number; post: Post }[]')
        ->and($analysis->inlineModelFqcns)->toBe(['owned' => [Post::class]])
        ->and($analysis->declaredTokenFqcns)->toBe([]);
});

// share() and a metadata provider's provide() lay their docblock over every key it types, so a position fill could only
// move a key's channels, as a model's would let a metadata companion import it.
test('a caller that lays the docblock over every key itself fills no position inside a key the body types, but a vague key', function () {
    $analysis = new MethodAnalysis(properties: [
        ['name' => 'owned', 'type' => '{ owner: unknown; n: unknown }[]', 'optional' => false, 'description' => ''],
        ['name' => 'pair', 'type' => 'unknown', 'optional' => false, 'description' => ''],
    ]);

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, 'nestedClasses'), fillsPositions: false);

    expect(array_column($analysis->properties, 'type', 'name'))
        ->toBe(['owned' => '{ owner: unknown; n: unknown }[]', 'pair' => '{ first: User; second: Post | null }'])
        ->and($analysis->inlineModelFqcns)->not->toHaveKey('owned');
});

test('an array<string, V> return fills each unknown position inside every key from V', function () {
    $analysis = refinedAnalysis([
        ['name' => 'a', 'type' => '{ n: unknown }'],
        ['name' => 'b', 'type' => '{ n: string }'],
    ], 'nestedRecord');

    expect(array_column($analysis->properties, 'type', 'name'))->toBe(['a' => '{ n: number }', 'b' => '{ n: string }']);
});

test('a workbench resource\'s @return fills the member a cache read leaves unknown, and the body types the rest', function () {
    config()->set('cache.default', 'array');
    cache()->put('mail.settings.ann@example.com', ['digest' => true]);
    $list = new RecipientList([new MailRecipient('Ann', 'ann@example.com'), new MailRecipient('Bob')]);

    expect(analyzedKeyTypes(RecipientSettingsResource::class))->toBe([
        'recipients' => '{ name: string | null; settings: { digest: boolean } | null }[]',
    ])->and(json_encode(new RecipientSettingsResource($list)->resolve(Request::create('/'))))->toBe(
        '{"recipients":[{"name":"Ann","settings":{"digest":true}},{"name":"Bob","settings":null}]}',
    );
});
