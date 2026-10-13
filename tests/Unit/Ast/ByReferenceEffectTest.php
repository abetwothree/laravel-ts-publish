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
