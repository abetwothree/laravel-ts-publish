<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnShapeFixture;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Chart;

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
