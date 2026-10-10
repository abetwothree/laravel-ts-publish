<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ResourceReindexing;

// removeMissingValues() re-indexes an array whose keys all pass is_numeric(): '06', '1.5' and '1e3' stay string keys in
// PHP, yet the filter still lists them.
it('re-indexes keys only when every one passes is_numeric()', function (array $keys, bool $reindexes) {
    expect(ResourceReindexing::reindexesKeys($keys))->toBe($reindexes);
})->with([
    'int keys from 0' => [[0, 1], true],
    'an int key not from 0' => [[42], true],
    'numeric-string keys PHP keeps as strings' => [['06', '1.5', '1e3', '-0', ' 1'], true],
    'a named key beside an int key' => [['a', 42], false],
    'no key' => [[], false],
]);

it('rewrites each array a resource re-indexes into the list it sends, at any depth', function (string $type, string $sent) {
    expect(ResourceReindexing::reindexedType($type))->toBe($sent);
})->with([
    'an int key' => ['{ "42": string }', 'string[]'],
    'int keys out of order' => ['{ "1": string; "0": number }', '(string | number)[]'],
    'numeric-string keys' => ['{ "06": string; "1.5": string }', 'string[]'],
    'a bare numeric key, as a docblock shape prints it' => ['{ 1: string; 2: string }', 'string[]'],
    'a nullable member' => ['{ "1": string | null }', '(string | null)[]'],
    'an optional member' => ['{ "42"?: string }', 'string[]'],
    'a nested array' => ['{ inner: { "7": string } }', '{ inner: string[] }'],
    'an array of arrays' => ['{ "1": string }[]', 'string[][]'],
    'a numeric-keyed array beside null' => ['{ "1": string } | null', 'string[] | null'],
    'a string-keyed record of arrays' => ['Record<string, { "1": string }>', 'Record<string, string[]>'],
    'a collection spread beside a numeric key' => ['Record<number, User> & { "0": boolean }', '(User | boolean)[]'],
    'a numeric index signature' => ['{ [key: number]: User }', 'User[]'],
    'an untyped member beside a typed one' => ['{ "1": unknown; "2": string }', 'unknown[]'],
]);

it('keeps every array that holds a named key, and every named type', function (string $type) {
    expect(ResourceReindexing::reindexedType($type))->toBe($type);
})->with([
    'a named key beside an int key' => ['{ a: number; "42": number }'],
    'a named key beside a numeric-string one' => ['{ a: number; "06": number }'],
    'a collection spread beside a named key' => ['Record<number, User> & { flag: boolean }'],
    'a resource' => ['UserResource'],
    'a generic' => ["Pick<User, 'id'>"],
    'a list' => ['string[]'],
    'a quoted key holding a colon' => ['{ "a:b": string }'],
    'a spread arm' => ["Omit<UserResource, 'x'> & { x: string }"],
]);
