<?php

use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use Illuminate\Support\Collection;

test('models collector works correctly', function () {
    $collector = resolve(ModelsCollector::class);

    $models = $collector->collect();

    expect($models)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(13);
});
