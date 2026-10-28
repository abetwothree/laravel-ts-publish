<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;

it('joins import maps, each name once per path, names and paths sorted', function (array $maps, array $expected) {
    expect(ImportMaps::merge(...$maps))->toBe($expected);
})->with([
    'none' => [[], []],
    'one map, normalized' => [[['@ui' => ['UiNode', 'UiGauge', 'UiNode']]], ['@ui' => ['UiGauge', 'UiNode']]],
    'two maps sharing a path' => [
        [['@ui' => ['UiNode'], '../enums' => ['StatusType']], ['@ui' => ['UiAmount', 'UiNode']]],
        ['../enums' => ['StatusType'], '@ui' => ['UiAmount', 'UiNode']],
    ],
    "a composed class's marker beside a module" => [
        [['ts-publish:class-type:App\\Chart' => ['ChartType']], ['@ui' => ['UiNode']]],
        ['@ui' => ['UiNode'], 'ts-publish:class-type:App\\Chart' => ['ChartType']],
    ],
]);
