<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnShapeFixture;

/**
 * @param  list<array{name: string, type: string, optional?: bool}>  $properties
 */
function refinedAnalysis(array $properties, string $method): MethodAnalysis
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

    resolve(ReturnShapeRefiner::class)->refine($analysis, new ReflectionMethod(ReturnShapeFixture::class, $method));

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

test('a method with no return docblock leaves every property alone', function () {
    $props = collect(refinedAnalysis([['name' => 'a', 'type' => 'unknown']], 'undocumented')->properties)->keyBy('name');

    expect($props['a'])->toMatchArray(['type' => 'unknown', 'optional' => false]);
});

test('an already-optional property stays optional when the shape does not say so', function () {
    $props = collect(refinedAnalysis([['name' => 'known', 'type' => 'unknown', 'optional' => true]], 'shaped')->properties)->keyBy('name');

    expect($props['known'])->toMatchArray(['type' => 'string', 'optional' => true]);
});
