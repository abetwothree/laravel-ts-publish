<?php

use AbeTwoThree\LaravelTsPublish\Runner;

it('can run the generator', function () {
    $runner = new Runner;
    $runner->run();

    expect(true)->toBeTrue();
});
