<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Effects\PropertyHooks;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\HookedProperties;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\InheritedHookedProperties;

it('names a property a get hook runs on', function (string $class, string $property, bool $runs) {
    expect(PropertyHooks::runsGetHook(new ReflectionClass($class), $property))->toBe($runs);
})->with([
    'a get hook' => [HookedProperties::class, 'reads', true],
    'a plain property' => [HookedProperties::class, 'plain', false],
    'a set hook only' => [HookedProperties::class, 'writes', false],
    'an inherited get hook' => [InheritedHookedProperties::class, 'reads', true],
    'a property the class lacks' => [HookedProperties::class, 'missing', false],
]);
