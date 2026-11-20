<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Support\ResourceDeclaration;

it('opens and closes a resource\'s declaration around its rows and each group of arms', function (array $resource, array $form) {
    $resource += ['tsExtends' => [], 'properties' => [], 'variants' => [], 'intersections' => []];
    $declaration = ResourceDeclaration::of($resource['tsExtends'], $resource['properties'], $resource['variants'], $resource['intersections']);

    expect([
        'interface' => $declaration->interface,
        'armsAlone' => $declaration->armsAlone,
        'open' => $declaration->interface || $declaration->armsAlone ? null : $declaration->open,
        'close' => $declaration->close,
        'closeArms' => $resource['variants'] === [] ? [] : [$declaration->closeArms(false), $declaration->closeArms(true)],
    ])->toBe($form);
})->with([
    'an interface' => [['properties' => ['id' => ['type' => 'number', 'optional' => false, 'description' => '']], 'tsExtends' => ['HasId']], [
        'interface' => true, 'armsAlone' => false, 'open' => null, 'close' => '}', 'closeArms' => [],
    ]],
    'its arms alone' => [['variants' => ['{ a: true } | { a: false }']], [
        'interface' => false, 'armsAlone' => true, 'open' => null, 'close' => null, 'closeArms' => [') & (', ');'],
    ]],
    'rows and arms' => [['properties' => ['id' => ['type' => 'number', 'optional' => false, 'description' => '']], 'variants' => ['{ a: true } | { a: false }']], [
        'interface' => false, 'armsAlone' => false, 'open' => '{', 'close' => '} & (', 'closeArms' => [') & (', ');'],
    ]],
    'an extends clause and two groups of arms' => [['variants' => ['A | B', 'C | D'], 'tsExtends' => ['HasId', 'HasTimestamps']], [
        'interface' => false, 'armsAlone' => false, 'open' => 'HasId & HasTimestamps & (', 'close' => null, 'closeArms' => [') & (', ');'],
    ]],
    'rows and intersections' => [['properties' => ['id' => ['type' => 'number', 'optional' => false, 'description' => '']], 'intersections' => ['A | B', 'C']], [
        'interface' => false, 'armsAlone' => false, 'open' => '{', 'close' => '} & (A | B) & (C);', 'closeArms' => [],
    ]],
    'arms and an intersection' => [['variants' => ['{ a: true } | { a: false }'], 'intersections' => ['C']], [
        'interface' => false, 'armsAlone' => false, 'open' => '(', 'close' => null, 'closeArms' => [') & (', ') & (C);'],
    ]],
    'an intersection alone' => [['tsExtends' => ['HasId'], 'intersections' => ['C']], [
        'interface' => false, 'armsAlone' => false, 'open' => 'HasId & (C);', 'close' => null, 'closeArms' => [],
    ]],
]);
