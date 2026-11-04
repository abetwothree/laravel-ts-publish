<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\CollectionSteps;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;

/** Parse one method call, written as a statement. */
function collectionStepCall(string $php): MethodCall
{
    $call = runtimeClassCall($php);
    assert($call instanceof MethodCall);

    return $call;
}

describe('chain', function () {
    test('lists the steps root first, and the expression the chain is rooted at', function () {
        [$steps, $root] = CollectionSteps::chain(collectionStepCall('collect($items)->filter()->map(fn ($i) => $i)->values()'));

        expect(array_column($steps, 'name'))->toBe(['filter', 'map', 'values'])
            ->and($steps[2]['node']->name->toString())->toBe('values')
            ->and($root)->toBeInstanceOf(FuncCall::class);
    });

    test('roots the chain at a call by a dynamic name', function () {
        [$steps, $root] = CollectionSteps::chain(collectionStepCall('$this->items->$step()->take(2)'));

        expect(array_column($steps, 'name'))->toBe(['take'])
            ->and($root)->toBeInstanceOf(MethodCall::class);

        [$steps, $root] = CollectionSteps::chain(collectionStepCall('$this->items->filter()'));

        expect(array_column($steps, 'name'))->toBe(['filter'])
            ->and($root)->toBeInstanceOf(PropertyFetch::class);
    });
});

describe('elementStep', function () {
    test('names the steps that hand back one item', function (string $method, ?string $kind) {
        expect(CollectionSteps::elementStep($method))->toBe($kind);
    })->with([
        'first' => ['first', 'nullableElement'],
        'get' => ['get', 'nullableElement'],
        'sole' => ['sole', 'element'],
        'firstOrFail' => ['firstOrFail', 'element'],
        'values' => ['values', null],
    ]);
});

describe('elementCall', function () {
    test('a first() or last() call hands back an item or null, unless it may send its default', function (string $php, bool $element) {
        $call = collectionStepCall($php);
        assert($call->name instanceof Identifier);

        expect(CollectionSteps::elementCall($call, $call->name->toString()))->toBe($element);
    })->with([
        'a bare first()' => ['$items->first()', true],
        'a last() with a callback' => ['$items->last(fn ($i) => $i)', true],
        'a first() with a default' => ['$items->first(null, 0)', false],
        'a named default' => ['$items->last(default: 0)', false],
        'a spread' => ['$items->first(...$arguments)', false],
        'a step that takes no default' => ['$items->firstWhere("a", 1)', false],
    ]);
});
