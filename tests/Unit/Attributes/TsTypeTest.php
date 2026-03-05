<?php

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;

test('TsType accepts a type string', function () {
    $attr = new TsType('CustomType');

    expect($attr->type)->toBe('CustomType');
});

test('TsType accepts complex type strings', function () {
    $attr = new TsType('Record<string, unknown> | null');

    expect($attr->type)->toBe('Record<string, unknown> | null');
});
