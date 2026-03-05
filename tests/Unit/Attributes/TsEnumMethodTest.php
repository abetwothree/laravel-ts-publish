<?php

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumMethod;

test('TsEnumMethod has empty defaults', function () {
    $attr = new TsEnumMethod;

    expect($attr->name)->toBe('')
        ->and($attr->description)->toBe('');
});

test('TsEnumMethod accepts name and description', function () {
    $attr = new TsEnumMethod(name: 'customName', description: 'Gets the icon');

    expect($attr->name)->toBe('customName')
        ->and($attr->description)->toBe('Gets the icon');
});
