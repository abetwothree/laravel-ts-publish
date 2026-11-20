<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust\LiteralGetter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\HookingLiteralGetters;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\LiteralGetters;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\UnsettingLiteralGetters;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\UnsettingLiteralGettersChild;

it('tells a method that only returns a literal, a constant or a property', function (string $method, bool $literal) {
    $runtime = new ReflectionClass(LiteralGetters::class);

    expect(LiteralGetter::returnsLiteral($runtime->getMethod($method), $runtime))->toBe($literal);
})->with([
    'a string' => ['string', true],
    'a number' => ['number', true],
    'a global constant' => ['constant', true],
    'a class constant' => ['classConstant', true],
    'an enum case' => ['enumCase', true],
    'a property the class declares' => ['property', true],
    'an array of those' => ['array', true],
    'a string, with a comment after it' => ['commented', true],
    'a local set first' => ['local', false],
    'a call\'s result' => ['call', false],
    'two strings joined' => ['joined', false],
    'a string holding a property' => ['interpolated', false],
    'a property the class lacks' => ['undeclared', false],
    'a static property' => ['staticProperty', false],
    'a property with a get hook' => ['hookedProperty', false],
    'a member of a property' => ['propertyMember', false],
    'an array holding a call\'s result' => ['arrayWithCall', false],
    'an array spreading a property' => ['arrayWithSpread', false],
    'a property assigned with ??=' => ['coalesceAssigned', false],
    'a new object' => ['built', false],
    'an empty body' => ['nothing', false],
]);

it('reads a property on the class the method runs on, but a private one its own class declares', function (
    string $name,
    string $runtime,
    bool $literal,
) {
    $method = new ReflectionMethod(LiteralGetters::class, $name);

    expect(LiteralGetter::returnsLiteral($method, new ReflectionClass($runtime)))->toBe($literal);
})->with([
    'a property the class declares, run on it' => ['property', LiteralGetters::class, true],
    'a property a subclass redeclares with a get hook' => ['property', HookingLiteralGetters::class, false],
    'a private property a subclass hooks its own of' => ['ownProperty', HookingLiteralGetters::class, true],
    'a property only a subclass declares, run on it' => ['belowProperty', HookingLiteralGetters::class, true],
    'a property only a subclass declares, run on the class' => ['belowProperty', LiteralGetters::class, false],
    'a property a subclass keeps private, read through __get()' => ['undeclared', HookingLiteralGetters::class, false],
    'a property a subclass unsets, read through __get()' => ['property', UnsettingLiteralGetters::class, false],
    'a property a parent\'s hidden constructor unsets' => ['property', UnsettingLiteralGettersChild::class, false],
    'a property beside one a subclass unsets' => ['ownProperty', UnsettingLiteralGetters::class, true],
]);
