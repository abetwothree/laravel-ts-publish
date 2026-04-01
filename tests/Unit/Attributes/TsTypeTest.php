<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

test('TsType accepts a type string', function () {
    $attr = new TsType('CustomType');

    expect($attr->type)->toBe('CustomType');
});

test('TsType accepts complex type strings', function () {
    $attr = new TsType('Record<string, unknown> | null');

    expect($attr->type)->toBe('Record<string, unknown> | null');
});

test('TsType accepts an array with type and import', function () {
    $attr = new TsType(['type' => 'ProductDimensions', 'import' => '@js/types/product']);

    expect($attr->type)->toBe(['type' => 'ProductDimensions', 'import' => '@js/types/product']);
});

test('TsType accepts an array with only type', function () {
    $attr = new TsType(['type' => 'InlineCustomType']);

    expect($attr->type)->toBe(['type' => 'InlineCustomType']);
});
