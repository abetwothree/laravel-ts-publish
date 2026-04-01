<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnumStaticMethod;

test('TsEnumStaticMethod has empty defaults', function () {
    $attr = new TsEnumStaticMethod;

    expect($attr->name)->toBe('')
        ->and($attr->description)->toBe('');
});

test('TsEnumStaticMethod accepts name and description', function () {
    $attr = new TsEnumStaticMethod(name: 'customStatic', description: 'Gets options');

    expect($attr->name)->toBe('customStatic')
        ->and($attr->description)->toBe('Gets options');
});
