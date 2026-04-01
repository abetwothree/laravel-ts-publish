<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Attributes\TsCase;

test('TsCase accepts name, value, and description', function () {
    $attr = new TsCase(name: 'Custom', value: 42, description: 'A test case');

    expect($attr->name)->toBe('Custom')
        ->and($attr->value)->toBe(42)
        ->and($attr->description)->toBe('A test case');
});

test('TsCase has empty string defaults', function () {
    $attr = new TsCase;

    expect($attr->name)->toBe('')
        ->and($attr->value)->toBe('')
        ->and($attr->description)->toBe('');
});

test('TsCase accepts partial parameters', function () {
    $attr = new TsCase(name: 'PartialName');

    expect($attr->name)->toBe('PartialName')
        ->and($attr->value)->toBe('')
        ->and($attr->description)->toBe('');
});
