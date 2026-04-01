<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;

test('TsCasts accepts string type overrides', function () {
    $attr = new TsCasts([
        'settings' => 'Record<string, unknown>',
        'metadata' => 'object | null',
    ]);

    expect($attr->types)->toBe([
        'settings' => 'Record<string, unknown>',
        'metadata' => 'object | null',
    ]);
});

test('TsCasts accepts array type overrides with import paths', function () {
    $attr = new TsCasts([
        'metadata' => ['type' => 'ProductMetadata | null', 'import' => '@js/types/product'],
    ]);

    expect($attr->types['metadata'])->toBe([
        'type' => 'ProductMetadata | null',
        'import' => '@js/types/product',
    ]);
});

test('TsCasts accepts mixed string and array overrides', function () {
    $attr = new TsCasts([
        'settings' => 'Record<string, unknown>',
        'metadata' => ['type' => 'ProductMetadata', 'import' => '@js/types'],
    ]);

    expect($attr->types)
        ->toHaveCount(2)
        ->and($attr->types['settings'])->toBe('Record<string, unknown>')
        ->and($attr->types['metadata'])->toBeArray();
});
