<?php

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

test('TsExtends accepts simple extends clause', function () {
    $attr = new TsExtends('CustomInterface', import: '@/types/custom');

    expect($attr->extends)->toBe('CustomInterface')
        ->and($attr->import)->toBe('@/types/custom')
        ->and($attr->types)->toBeNull();
});

test('TsExtends accepts generic extends with explicit types', function () {
    $attr = new TsExtends(
        'Pick<Auditable, "created_by" | "updated_by">',
        import: '@/types/audit',
        types: ['Auditable'],
    );

    expect($attr->extends)->toBe('Pick<Auditable, "created_by" | "updated_by">')
        ->and($attr->import)->toBe('@/types/audit')
        ->and($attr->types)->toBe(['Auditable']);
});

test('TsExtends allows null import for globally known types', function () {
    $attr = new TsExtends('BaseFields');

    expect($attr->extends)->toBe('BaseFields')
        ->and($attr->import)->toBeNull()
        ->and($attr->types)->toBeNull();
});
