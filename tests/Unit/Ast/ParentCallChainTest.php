<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ParentCallChain;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\HoldsParentPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\InheritsSpreadPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\MergesParentPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\NestsParentPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ParentCallBase;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReturnsParentPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SpreadsNestingPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SpreadsParentPayload;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\SpreadsSpreadPayload;

beforeEach(function () {
    require_once __DIR__.'/Fixtures/ParentCallsInOneFile.php';
});

// The base's own key `a` is in what PHP returns exactly where the chain reaches the base.
it('follows each parent:: call whose keys the body returns, and stops at one it nests', function (string $class, array $chain) {
    expect(resolve(ParentCallChain::class)->declaringClasses($class, 'payload'))->toBe($chain)
        ->and(array_key_exists('a', new $class()->payload()))->toBe(in_array(ParentCallBase::class, $chain, true));
})->with([
    'a spread' => [SpreadsParentPayload::class, [SpreadsParentPayload::class, ParentCallBase::class]],
    'an array_merge()' => [MergesParentPayload::class, [MergesParentPayload::class, ParentCallBase::class]],
    'a whole return on one branch' => [ReturnsParentPayload::class, [ReturnsParentPayload::class, ParentCallBase::class]],
    'a local written by += and returned' => [HoldsParentPayload::class, [HoldsParentPayload::class, ParentCallBase::class]],
    'calls under keys and in a closure' => [NestsParentPayload::class, [NestsParentPayload::class]],
    'an inherited body' => [InheritsSpreadPayload::class, [SpreadsParentPayload::class, ParentCallBase::class]],
    'two spreads up the chain' => [
        SpreadsSpreadPayload::class,
        [SpreadsSpreadPayload::class, SpreadsParentPayload::class, ParentCallBase::class],
    ],
    'a spread of a body that nests its own parent call' => [SpreadsNestingPayload::class, [SpreadsNestingPayload::class, NestsParentPayload::class]],
    'no parent:: call' => [ParentCallBase::class, [ParentCallBase::class]],
]);

it('has no chain for a method the class lacks', function () {
    expect(resolve(ParentCallChain::class)->declaringClasses(SpreadsParentPayload::class, 'notAMethod'))->toBe([])
        ->and(resolve(ParentCallChain::class)->declaringClasses('Not\A\Class', 'payload'))->toBe([]);
});
