<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ByReferenceEffect;

it('keeps a value only while it has the form the call keeps', function (ByReferenceEffect $effect, string $type, array $values, bool $keeps) {
    expect($effect->keeps($type, $values))->toBe($keeps);
})->with([
    'ksort() keeps a list' => [ByReferenceEffect::KeepsAny, 'string[]', [], true],
    'ksort() keeps an object' => [ByReferenceEffect::KeepsAny, '{ a: number }', [], true],
    'sort() keeps a list' => [ByReferenceEffect::KeepsList, 'string[]', [], true],
    'sort() keeps a list of a union' => [ByReferenceEffect::KeepsList, '(string | number)[]', [], true],
    'sort() keeps a nullable list, since null never gets past it' => [ByReferenceEffect::KeepsList, 'string[] | null', [], true],
    'sort() re-indexes an object' => [ByReferenceEffect::KeepsList, '{ a: number }', [], false],
    'sort() moves a tuple\'s members' => [ByReferenceEffect::KeepsList, '[number, string]', [], false],
    'sort() of null alone' => [ByReferenceEffect::KeepsList, 'null', [], false],
    'sort() of unknown' => [ByReferenceEffect::KeepsList, 'unknown', [], false],
    'asort() keeps an object' => [ByReferenceEffect::KeepsObject, '{ a: number; b: number }', [], true],
    'asort() keeps a Record' => [ByReferenceEffect::KeepsObject, 'Record<string, number>', [], true],
    'asort() turns a list into an object' => [ByReferenceEffect::KeepsObject, 'string[]', [], false],
    'a count keeps a number' => [ByReferenceEffect::KeepsNumber, 'number', [], true],
    'a count replaces a string' => [ByReferenceEffect::KeepsNumber, 'string', [], false],
    'a count replaces null' => [ByReferenceEffect::KeepsNumber, 'number | null', [], false],
    'array_push() of the element type' => [ByReferenceEffect::Appends, 'string[]', ['string'], true],
    'array_push() of an arm of the element type' => [ByReferenceEffect::Appends, '(string | null)[]', ['null'], true],
    'array_push() into an unknown element' => [ByReferenceEffect::Appends, 'unknown[]', ['number'], true],
    'array_push() of another type' => [ByReferenceEffect::Appends, 'string[]', ['number'], false],
    'array_push() of a wider type' => [ByReferenceEffect::Appends, 'string[]', ['string | number'], false],
    'array_push() into an object' => [ByReferenceEffect::Appends, '{ a: number }', ['number'], false],
    'a walk writing a list\'s element type' => [ByReferenceEffect::WritesElements, 'string[]', ['string'], true],
    'a walk writing every member\'s type' => [ByReferenceEffect::WritesElements, '{ a: number; b?: number }', ['number'], true],
    'a walk writing a Record\'s value type' => [ByReferenceEffect::WritesElements, 'Record<string, number>', ['number'], true],
    'a walk writing one member\'s type alone' => [ByReferenceEffect::WritesElements, '{ a: number; b: string }', ['string'], false],
    'a walk writing nothing' => [ByReferenceEffect::WritesElements, '{ a: number }', [], true],
    'a walk over a class' => [ByReferenceEffect::WritesElements, 'User', [], false],
    'a replacing call' => [ByReferenceEffect::Replaces, 'string[]', [], false],
]);

// A call on a key of a variable keeps the variable while the member at that key keeps its form.
it('keeps a value while the member a key path names keeps the form the call keeps', function (ByReferenceEffect $effect, string $type, array $path, bool $keeps) {
    expect($effect->keeps($type, [], $path))->toBe($keeps);
})->with([
    'sort() of a list member' => [ByReferenceEffect::KeepsList, '{ x: number[]; y: string }', ['x'], true],
    'sort() of a nested list member' => [ByReferenceEffect::KeepsList, '{ x: { y: number[] } }', ['x', 'y'], true],
    'sort() of a Record\'s list' => [ByReferenceEffect::KeepsList, 'Record<string, number[]>', ['k'], true],
    'sort() of a list in a list' => [ByReferenceEffect::KeepsList, 'number[][]', ['0'], true],
    'sort() of an optional list member' => [ByReferenceEffect::KeepsList, '{ x?: number[] }', ['x'], true],
    'sort() of an object member, which it re-indexes' => [ByReferenceEffect::KeepsList, '{ x: { b: number } }', ['x'], false],
    'ksort() of any member' => [ByReferenceEffect::KeepsAny, '{ x: { b: number } }', ['x'], true],
    'a member the type does not hold' => [ByReferenceEffect::KeepsList, '{ x: number[] }', ['y'], false],
    'a member of a list by a name' => [ByReferenceEffect::KeepsList, 'number[][]', ['x'], false],
    'a member of an opaque type' => [ByReferenceEffect::KeepsList, 'User', ['x'], false],
    'a member typed unknown, which any call leaves unknown' => [ByReferenceEffect::KeepsList, '{ x: unknown }', ['x'], true],
    'a member below one typed unknown' => [ByReferenceEffect::KeepsList, '{ x: unknown }', ['x', 'y'], true],
    'a replacing call on a member typed unknown' => [ByReferenceEffect::Replaces, '{ x: unknown }', ['x'], false],
]);

it('keeps a value a recursive walk writes while every leaf it visits holds each written value', function (string $type, array $values, bool $keeps) {
    expect(ByReferenceEffect::WritesLeaves->keeps($type, $values))->toBe($keeps);
})->with([
    'a nested object\'s leaves' => ['{ a: { b: number } }', ['number'], true],
    'a list of lists\' leaves' => ['number[][]', ['number'], true],
    'leaves beside a nested array' => ['{ a: number; b: { c: number[] } }', ['number'], true],
    'a tuple\'s leaves' => ['[number, { a: number }]', ['number'], true],
    'a leaf of a union holding the value' => ['{ a: number | null }', ['number'], true],
    'a leaf of another type' => ['{ a: { b: string } }', ['number'], false],
    'a null leaf beside a nested array' => ['{ a: number[] | null }', ['number'], false],
    'a walk that writes nothing' => ['{ a: { b: string } }', [], true],
    'a value that is no array' => ['User', [], false],
]);
