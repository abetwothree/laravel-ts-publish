<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\ReadsCollectedItems;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\SpellsKeyedCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\CollectionSteps;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use ReflectionMethod;

/**
 * A collection chain rooted at `collect($arg)`: a list's element type survives `values`, `all`, `filter`, `reject`
 * and `unique`, and at most one `map()` with any callable maps any array `$arg` holds, keeping its keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CollectionPipelineHandler implements ExpressionHandler
{
    use BindsMapCallbacks;
    use ReadsCollectedItems;
    use SpellsKeyedCollections;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof MethodCall) {
            return null;
        }

        [$steps, $root] = CollectionSteps::chain($expr);
        $items = CallArguments::collectArgument($root);
        $rootResult = $items === null ? null : $this->collectedItems($items, $scope, $engine);

        if ($rootResult === null || ! $root instanceof FuncCall) {
            return null;
        }

        // Only a `T[]` root lists its elements here, never an `Array<T>` one; any other root is only mapped.
        $listed = str_ends_with($rootResult['type'], '[]') && ArrayArms::listElement($rootResult['type']) !== null;
        $identityOps = ['values', 'all', 'filter', 'reject', 'unique'];
        $mapNode = null;

        // collect() over a list starts keyed 0..n-1; each op below says whether that still holds.
        $sequentialKeys = true;

        foreach ($steps as $step) {
            if (in_array($step['name'], $identityOps, true) && ($listed || $step['name'] === 'all')) {
                $sequentialKeys = $step['name'] === 'all'
                    ? $sequentialKeys
                    : ! CollectionSteps::keyedAfter($step['node'], $step['name'], ! $sequentialKeys, false);

                continue;
            }

            if ($step['name'] === 'map' && $mapNode === null) {
                // map() preserves the receiver's keys, so it neither breaks nor restores sequentiality.
                $mapNode = $step['node'];

                continue;
            }

            // Unsupported op, including a second map().
            return null;
        }

        if ($mapNode === null) {
            return ! $listed ? null : [
                ...$rootResult,
                'type' => $sequentialKeys ? $rootResult['type'] : $this->keyedObjectArm($rootResult['type']),
                'optional' => false,
            ];
        }

        $callback = CallArguments::for($mapNode, new ReflectionMethod(Collection::class, 'map'))->named('callback')?->value;
        $mapped = $callback === null ? null : $this->mappedArrayResult(
            $callback,
            $rootResult,
            $steps[count($steps) - 1]['name'] === 'all',
            $scope,
            $engine,
            $this->elementModel($items, $scope),
        );

        if ($mapped === null || ! $listed) {
            // A kept Collection's keys reach json_encode() as they are, since a resource's filter never enters one.
            return $mapped === null || $steps[count($steps) - 1]['name'] === 'all' ? $mapped : [...$mapped, 'keepsKeys' => true];
        }

        return $sequentialKeys ? $mapped : [...$mapped, 'type' => $this->keyedObjectArm($mapped['type'])];
    }

    /**
     * The model each element of a `collect($items)` root holds, as the receiver resolver names it for `$items`.
     *
     * @return class-string<Model>|null
     */
    private function elementModel(Expr $items, AnalysisScope $scope): ?string
    {
        return resolve(ReceiverClassResolver::class)->resolve($items, $scope)?->elementModel;
    }
}
