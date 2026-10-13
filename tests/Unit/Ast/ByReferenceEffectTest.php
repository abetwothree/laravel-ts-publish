<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\ByReferenceEffect;

/**
 * What an effect leaves in a value of a type, when the call writes the given value types whatever the element.
 *
 * @param  list<string>  $values
 * @param  list<string>  $path
 */
function byReferenceLeft(ByReferenceEffect $effect, string $type, array $values = [], array $path = []): ?string
{
    return $effect->left($type, fn (string $element, string $key): array => $values, $path);
}

it('leaves a value the type it held while it has the form the call keeps', function (ByReferenceEffect $effect, string $type, array $values, ?string $left) {
    expect(byReferenceLeft($effect, $type, $values))->toBe($left);
})->with([
    'ksort() keeps a list' => [ByReferenceEffect::KeepsAny, 'string[]', [], 'string[]'],
    'ksort() keeps an object' => [ByReferenceEffect::KeepsAny, '{ a: number }', [], '{ a: number }'],
    'sort() keeps a list' => [ByReferenceEffect::KeepsList, 'string[]', [], 'string[]'],
    'sort() keeps a list of a union' => [ByReferenceEffect::KeepsList, '(string | number)[]', [], '(string | number)[]'],
    'sort() keeps a nullable list, since null never gets past it' => [ByReferenceEffect::KeepsList, 'string[] | null', [], 'string[] | null'],
    'sort() re-indexes an object' => [ByReferenceEffect::KeepsList, '{ a: number }', [], null],
    'sort() moves a tuple\'s members' => [ByReferenceEffect::KeepsList, '[number, string]', [], null],
    'sort() of null alone' => [ByReferenceEffect::KeepsList, 'null', [], null],
    'sort() of unknown' => [ByReferenceEffect::KeepsList, 'unknown', [], null],
    'asort() keeps an object' => [ByReferenceEffect::KeepsObject, '{ a: number; b: number }', [], '{ a: number; b: number }'],
    'asort() keeps a Record' => [ByReferenceEffect::KeepsObject, 'Record<string, number>', [], 'Record<string, number>'],
    'asort() keys a list out of order' => [ByReferenceEffect::KeepsObject, 'string[]', [], 'Record<number, string>'],
    'asort() keys a list of a union out of order' => [ByReferenceEffect::KeepsObject, '(string | number)[]', [], 'Record<number, string | number>'],
    'asort() keys a tuple out of order' => [ByReferenceEffect::KeepsObject, '[number, string]', [], 'Record<number, number | string>'],
    'asort() keys a nullable list out of order' => [ByReferenceEffect::KeepsObject, 'string[] | null', [], 'Record<number, string> | null'],
    'asort() of a class' => [ByReferenceEffect::KeepsObject, 'User', [], null],
    'a count keeps a number' => [ByReferenceEffect::KeepsNumber, 'number', [], 'number'],
    'a count replaces a string' => [ByReferenceEffect::KeepsNumber, 'string', [], null],
    'a count replaces null' => [ByReferenceEffect::KeepsNumber, 'number | null', [], null],
    'array_push() of the element type' => [ByReferenceEffect::Appends, 'string[]', ['string'], 'string[]'],
    'array_push() of an arm of the element type' => [ByReferenceEffect::Appends, '(string | null)[]', ['null'], '(string | null)[]'],
    'array_push() into an unknown element' => [ByReferenceEffect::Appends, 'unknown[]', ['number'], 'unknown[]'],
    'array_push() of another type' => [ByReferenceEffect::Appends, 'string[]', ['number'], null],
    'array_push() of a wider type' => [ByReferenceEffect::Appends, 'string[]', ['string | number'], null],
    'array_push() into an object' => [ByReferenceEffect::Appends, '{ a: number }', ['number'], null],
    'a walk writing a list\'s element type' => [ByReferenceEffect::WritesElements, 'string[]', ['string'], 'string[]'],
    'a walk writing every member\'s type' => [ByReferenceEffect::WritesElements, '{ a: number; b?: number }', ['number'], '{ a: number; b?: number }'],
    'a walk writing a Record\'s value type' => [ByReferenceEffect::WritesElements, 'Record<string, number>', ['number'], 'Record<string, number>'],
    'a walk writing a tuple\'s member types' => [ByReferenceEffect::WritesElements, '[number, number]', ['number'], '[number, number]'],
    'a walk writing one member\'s type alone' => [ByReferenceEffect::WritesElements, '{ a: number; b: string }', ['string'], null],
    'a walk writing nothing' => [ByReferenceEffect::WritesElements, '{ a: number }', [], '{ a: number }'],
    'a walk over a class' => [ByReferenceEffect::WritesElements, 'User', [], null],
    'a replacing call' => [ByReferenceEffect::Replaces, 'string[]', [], null],
]);

// An API resource re-indexes a list a key-keeping sort reorders, so there it sends the list it was.
it('leaves a list a key-keeping sort reorders as it was where the response re-indexes it', function (string $type, array $path, string $left) {
    expect(ByReferenceEffect::KeepsObject->left($type, fn (): array => [], $path, reindexed: true))->toBe($left);
})->with([
    'a list' => ['string[]', [], 'string[]'],
    'a list member' => ['{ x: string[]; c: { 3: string } }', ['x'], '{ x: string[]; c: { 3: string } }'],
    'an object' => ['{ b: number }', [], '{ b: number }'],
]);

// A walk's callback reads its element and its key: a list's key is an int, an object's or a string Record's a string.
it('checks what a walk writes into each element against that element, under its own key', function (string $type, ?string $left) {
    expect(ByReferenceEffect::WritesElements->left($type, fn (string $element, string $key): array => [$element, $key]))
        ->toBe($left);
})->with([
    'a list, whose int key is no string' => ['string[]', null],
    'a list of numbers under int keys' => ['number[]', 'number[]'],
    'an object of strings under string keys' => ['{ a: string; b: string }', '{ a: string; b: string }'],
    'an object under numeric keys' => ['{ 1: string }', null],
    'a string Record' => ['Record<string, string>', 'Record<string, string>'],
    'a number Record' => ['Record<number, number>', 'Record<number, number>'],
]);

// A call on a key of a variable keeps the variable while the member at that key keeps its form.
it('leaves a value the member a key path names keeps, rewritten where a key-keeping sort keys a list member', function (ByReferenceEffect $effect, string $type, array $path, ?string $left) {
    expect(byReferenceLeft($effect, $type, [], $path))->toBe($left);
})->with([
    'sort() of a list member' => [ByReferenceEffect::KeepsList, '{ x: number[]; y: string }', ['x'], '{ x: number[]; y: string }'],
    'sort() of a nested list member' => [ByReferenceEffect::KeepsList, '{ x: { y: number[] } }', ['x', 'y'], '{ x: { y: number[] } }'],
    'sort() of a Record\'s list' => [ByReferenceEffect::KeepsList, 'Record<string, number[]>', ['k'], 'Record<string, number[]>'],
    'sort() of a list in a list' => [ByReferenceEffect::KeepsList, 'number[][]', ['0'], 'number[][]'],
    'sort() of an optional list member' => [ByReferenceEffect::KeepsList, '{ x?: number[] }', ['x'], '{ x?: number[] }'],
    'sort() of an object member, which it re-indexes' => [ByReferenceEffect::KeepsList, '{ x: { b: number } }', ['x'], null],
    'ksort() of any member' => [ByReferenceEffect::KeepsAny, '{ x: { b: number } }', ['x'], '{ x: { b: number } }'],
    'asort() of a list member' => [ByReferenceEffect::KeepsObject, '{ x: string[]; y: number }', ['x'], '{ x: Record<number, string>; y: number }'],
    'asort() of a nested list member' => [ByReferenceEffect::KeepsObject, '{ x: { y: string[] } }', ['x', 'y'], '{ x: { y: Record<number, string> } }'],
    'asort() of a list in a Record, whose other keys keep theirs' => [ByReferenceEffect::KeepsObject, 'Record<string, string[]>', ['k'], 'Record<string, string[] | Record<number, string>>'],
    'asort() of a list member beside a Record arm' => [ByReferenceEffect::KeepsObject, 'Record<string, string[]> | { x: string[] }', ['x'], 'Record<string, string[] | Record<number, string>> | { x: Record<number, string> }'],
    'a member the type does not hold' => [ByReferenceEffect::KeepsList, '{ x: number[] }', ['y'], null],
    'a member of a list by a name' => [ByReferenceEffect::KeepsList, 'number[][]', ['x'], null],
    'a member of an opaque type' => [ByReferenceEffect::KeepsList, 'User', ['x'], null],
    'a member typed unknown, which any call leaves unknown' => [ByReferenceEffect::KeepsList, '{ x: unknown }', ['x'], '{ x: unknown }'],
    'a member below one typed unknown' => [ByReferenceEffect::KeepsList, '{ x: unknown }', ['x', 'y'], '{ x: unknown }'],
    'a replacing call on a member typed unknown' => [ByReferenceEffect::Replaces, '{ x: unknown }', ['x'], null],
]);

it('keeps a value a recursive walk writes while every leaf it visits holds each written value', function (string $type, array $values, ?string $left) {
    expect(byReferenceLeft(ByReferenceEffect::WritesLeaves, $type, $values))->toBe($left);
})->with([
    'a nested object\'s leaves' => ['{ a: { b: number } }', ['number'], '{ a: { b: number } }'],
    'a list of lists\' leaves' => ['number[][]', ['number'], 'number[][]'],
    'leaves beside a nested array' => ['{ a: number; b: { c: number[] } }', ['number'], '{ a: number; b: { c: number[] } }'],
    'a tuple\'s leaves' => ['[number, { a: number }]', ['number'], '[number, { a: number }]'],
    'a leaf of a union holding the value' => ['{ a: number | null }', ['number'], '{ a: number | null }'],
    'a leaf of another type' => ['{ a: { b: string } }', ['number'], null],
    'a null leaf beside a nested array' => ['{ a: number[] | null }', ['number'], null],
    'a walk that writes nothing' => ['{ a: { b: string } }', [], '{ a: { b: string } }'],
    'a value that is no array' => ['User', [], null],
]);
