<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Calls\NativeTypeNames;

it('names each type a native type spells, lowercased', function (int $position, array $names) {
    $parameters = new ReflectionFunction(
        static fn (?callable $a, Closure|string|null $b, (Countable&Traversable)|null $c, $d) => null,
    )->getParameters();

    expect(NativeTypeNames::of($parameters[$position]->getType()))->toBe($names);
})->with([
    'a nullable type' => [0, ['callable']],
    'a union' => [1, ['closure', 'string', 'null']],
    'an intersection in a union' => [2, ['countable', 'traversable', 'null']],
    'no type' => [3, []],
]);
