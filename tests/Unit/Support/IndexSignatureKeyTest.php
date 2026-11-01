<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\IndexSignatureKey;

describe('fromParts', function () {
    test('literal text around a dynamic part encodes as a template-literal signature', function (array $parts, ?string $expected) {
        expect(IndexSignatureKey::fromParts($parts))->toBe($expected);
    })->with([
        'a suffix' => [[null, '_tag'], '[key: `${string}_tag`]'],
        'a prefix and a suffix' => [['pre_', null, '_post'], '[key: `pre_${string}_post`]'],
        'a backslash, a `${` and a CR are escaped' => [[null, "\\\${\r"], '[key: `${string}\\\\\\${\\r`]'],
        'no dynamic part' => [['_tag'], null],
        'no literal part' => [[null, null], null],
        'a backtick declines' => [[null, '`'], null],
    ]);

    test('literalSegments() reads back exactly the literal text fromParts() wrote', function () {
        $name = (string) IndexSignatureKey::fromParts(['a\\', null, "\${b\r", null, 'c']);

        expect(IndexSignatureKey::literalSegments($name))->toBe(['a\\', "\${b\r", 'c']);
    });
});

describe('is', function () {
    test('only a generated index signature is one, never a property name', function (string $key, bool $expected) {
        expect(IndexSignatureKey::is($key))->toBe($expected);
    })->with([
        'string' => ['[key: string]', true],
        'number' => ['[key: number]', true],
        'a template' => ['[key: `${string}_tag`]', true],
        'a property name' => ['price_tag', false],
    ]);
});

describe('covers', function () {
    // TypeScript checks a property against each index signature whose key type the property's name belongs to (TS2411).
    test('a signature covers the keys TypeScript checks against it', function (string $signature, string $key, bool $expected) {
        expect(IndexSignatureKey::covers($signature, $key))->toBe($expected);
    })->with([
        'string covers any key' => ['[key: string]', 'auth', true],
        'number covers a numeric key' => ['[key: number]', '1', true],
        'number covers no other key' => ['[key: number]', 'auth', false],
        'a template covers a key its pattern matches' => ['[key: `${string}_tag`]', 'x_tag', true],
        'a template covers no other key' => ['[key: `${string}_tag`]', 'x_tag.b', false],
        'a property name covers nothing' => ['price_tag', 'price_tag', false],
    ]);
});

describe('castSpellings', function () {
    test('each other spelling a #[TsCasts] key may use for a signature name', function () {
        expect(IndexSignatureKey::castSpellings('[key: `${string}\\\\\\r`]'))->toBe([
            '[key: `${string}\\\\r`]',
            '[key: `${string}\\\\'."\r".'`]',
            '[key: `${string}\\'."\r".'`]',
        ])->and(IndexSignatureKey::castSpellings('[key: `${string}_tag`]'))->toBe([]);
    });
});
