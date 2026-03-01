<?php

use AbeTwoThree\LaravelTsPublish\Collectors\EnumsCollector;
use Illuminate\Support\Collection;

test('enums collector works correctly', function () {
    $collector = resolve(EnumsCollector::class);

    $enums = $collector->collect();

    expect($enums)
        ->toBeInstanceOf(Collection::class);
    // ->toHaveCount(2)
});
