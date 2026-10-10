<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TopLevelRenumbering;

// array_merge() renumbers only the keys PHP stores as ints: '06' and '1.5' stay strings, while '-1' is the int -1.
it('renumbers int-like names from 0 in source order and keeps every other name', function (array $names, array $renumbered) {
    expect(TopLevelRenumbering::renumber($names))->toBe($renumbered);
})->with([
    'int keys among named ones' => [['id', '42', 'x', '7'], ['id' => 'id', '42' => '0', 'x' => 'x', '7' => '1']],
    'numeric strings PHP keeps as strings' => [['06', '1.5'], ['06' => '06', '1.5' => '1.5']],
    'a negative int key' => [['-1', 'a'], ['-1' => '0', 'a' => 'a']],
    'no name' => [[], []],
]);

it('re-keys a map by the renumbering, keeping its order', function () {
    $renumbered = TopLevelRenumbering::renumber(['id', '42', '7']);

    expect(TopLevelRenumbering::rekey(['id' => 'number', '7' => 'string', '42' => 'boolean'], $renumbered))
        ->toBe(['id' => 'number', '1' => 'string', '0' => 'boolean']);
});

it('warns once for each key Laravel publishes on another number', function () {
    $renumbered = TopLevelRenumbering::renumber(['0', 'id', '42']);

    TopLevelRenumbering::warnRenumbered('App\Events\Shipped', 'a broadcast payload', $renumbered);
    TopLevelRenumbering::warnRenumbered('App\Events\Shipped', 'a broadcast payload', $renumbered);

    expect(AnalysisWarnings::all())->toBe([[
        'subject' => 'App\Events\Shipped',
        'message' => 'The key "42" is renumbered to "1" by Laravel at the top level of a broadcast payload; use a string key.',
    ]]);
});
