<?php

declare(strict_types=1);

it('has a laravel/prompts version that provides callout and elements', function () {
    expect(function_exists('Laravel\Prompts\callout'))->toBeTrue()
        ->and(class_exists('Laravel\Prompts\Elements\Element'))->toBeTrue()
        ->and(class_exists('Laravel\Prompts\Elements\BulletedList'))->toBeTrue();
});
