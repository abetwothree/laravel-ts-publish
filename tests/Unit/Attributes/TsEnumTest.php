<?php

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;

test('TsEnum accepts a name string', function () {
    $attr = new TsEnum('ShipmentStatus');

    expect($attr->name)->toBe('ShipmentStatus');
});

test('TsEnum targets classes only', function () {
    $reflection = new ReflectionClass(TsEnum::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS);
});
