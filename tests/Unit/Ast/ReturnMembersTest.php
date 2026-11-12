<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape\ReturnMembers;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\ReturnShape\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnShapeFixture;

/**
 * The members of one of ReturnShapeFixture's methods.
 */
function returnMembersOfFixture(string $method): ReturnMembers
{
    return ReturnMembers::of(new ReflectionMethod(ReturnShapeFixture::class, $method));
}

test('reads a member by its name, and an optional one by its name without the `?`', function () {
    $members = returnMembersOfFixture('spelledKeys');

    expect($members->member('rows')['type'] ?? null)->toBe('number[]')
        ->and($members->member('0')['type'] ?? null)->toBe('string')
        ->and($members->member('missing'))->toBeNull()
        ->and($members->isOptional('rows'))->toBeTrue()
        ->and($members->isOptional('0'))->toBeFalse()
        ->and($members->isOptional('missing'))->toBeFalse();
});

test('gives every name the value an `array<string, V>` declares, none of them optional', function () {
    $members = returnMembersOfFixture('record');

    expect($members->member('any')['type'] ?? null)->toBe('string')
        ->and($members->member('other')['type'] ?? null)->toBe('string')
        ->and($members->isOptional('any'))->toBeFalse();
});

test('gives no member where the `@return` is neither a shape nor a string-keyed record', function (string $method) {
    expect(returnMembersOfFixture($method)->member('a'))->toBeNull();
})->with(['undocumented', 'records']);

test('lists each key its `@return` types, spelled as the PHP key reads it', function (string $method, array|bool $keys) {
    expect(returnMembersOfFixture($method)->typedKeys())->toBe($keys);
})->with([
    'a shape, its `mixed` member left out' => ['vague', ['list', 'record', 'precise']],
    'an optional key and an int key' => ['spelledKeys', ['rows', '0']],
    'an informative record' => ['record', true],
    'a record of shapes' => ['nestedRecord', true],
    'a record of `mixed`' => ['mixedRecord', []],
    'a union of records' => ['records', []],
    'no docblock' => ['undocumented', []],
])->group('gap-mode');

test('refine() merges the members it is handed, not those of the method it reads names against', function () {
    $properties = [
        ['name' => 'list', 'type' => 'unknown[]', 'optional' => false, 'description' => ''],
        ['name' => 'record', 'type' => 'Record<string, unknown>', 'optional' => false, 'description' => ''],
        ['name' => 'precise', 'type' => 'unknown', 'optional' => false, 'description' => ''],
    ];
    $handed = new MethodAnalysis(properties: $properties);
    $own = new MethodAnalysis(properties: $properties);
    $vague = new ReflectionMethod(ReturnShapeFixture::class, 'vague');

    resolve(ReturnShapeRefiner::class)->refine(
        $handed,
        new ReflectionMethod(ReturnShapeFixture::class, 'undocumented'),
        ReturnMembers::of($vague),
    );
    resolve(ReturnShapeRefiner::class)->refine($own, $vague, ReturnMembers::of($vague));

    expect(array_column($handed->properties, 'type', 'name'))->toBe([
        'list' => 'string[]',
        'record' => 'Record<string, number>',
        'precise' => 'number',
    ])->and($handed->properties)->toBe($own->properties);
});
