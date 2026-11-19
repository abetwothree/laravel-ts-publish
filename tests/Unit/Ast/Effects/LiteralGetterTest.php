<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust\LiteralGetter;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects\LiteralGetters;

it('tells a method that only returns a literal, a constant or a property', function (string $method, bool $literal) {
    expect(LiteralGetter::returnsLiteral(new ReflectionMethod(LiteralGetters::class, $method)))->toBe($literal);
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
    'an empty body' => ['nothing', false],
]);
