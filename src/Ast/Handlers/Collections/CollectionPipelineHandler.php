<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
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
 * A collection chain rooted at `collect($arg)`: the element type comes from `$arg`, survives
 * `values`/`all`/`filter`/`reject`/`unique` plus at most one `map(closure)`, and is array-wrapped back out.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CollectionPipelineHandler implements ExpressionHandler
{
    use BindsMapCallbacks;
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
        $rootResult = $this->collectRootResult($root, $engine);

        if ($rootResult === null || ! $root instanceof FuncCall) {
            return null;
        }

        // Only a `T[]` root lists its elements here, never an `Array<T>` one.
        $element = str_ends_with($rootResult['type'], '[]') ? ArrayArms::listElement($rootResult['type']) : null;

        if ($element === null) {
            return null;
        }

        $identityOps = ['values', 'all', 'filter', 'reject', 'unique'];
        $mapNode = null;

        // collect() over a list starts keyed 0..n-1; each op below says whether that still holds.
        $sequentialKeys = true;

        foreach ($steps as $step) {
            if (in_array($step['name'], $identityOps, true)) {
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
            return [
                ...$rootResult,
                'type' => $sequentialKeys ? $rootResult['type'] : $this->keyedObjectArm($rootResult['type']),
                'optional' => false,
            ];
        }

        $bodyResult = $this->resolveMapCallback(
            CallArguments::for($mapNode, new ReflectionMethod(Collection::class, 'map'))->named('callback')?->value,
            $this->elementModel($root, $rootResult, $element, $scope),
            ['type' => $element, 'optional' => false],
            $steps[count($steps) - 1]['name'] === 'all',
            $scope,
            $engine,
        );

        return $this->mappedList($bodyResult, ! $sequentialKeys, false);
    }

    /**
     * Resolve the argument of a `collect($arg)` root, or decline a chain rooted at anything else.
     *
     * @return ValueExpressionResult|null
     */
    private function collectRootResult(Expr $root, ExpressionEngine $engine): ?array
    {
        $argument = CallArguments::collectArgument($root);
        $result = $argument === null ? null : $engine->resolve($argument);

        // The empty tuple, such as a `list{}` return, holds no element: it is the empty list a literal `[]` prints.
        return $result !== null && $result['type'] === '[]' ? [...$result, 'type' => 'never[]'] : $result;
    }

    /**
     * The model each element of a `collect($arg)` root holds: the element model the receiver resolver names for `$arg`,
     * else the model its value carries when the element type is that model.
     *
     * @param  ValueExpressionResult  $rootResult
     * @return class-string<Model>|null
     */
    private function elementModel(FuncCall $root, array $rootResult, string $element, AnalysisScope $scope): ?string
    {
        $argument = CallArguments::collectArgument($root);
        $named = $argument === null ? null : resolve(ReceiverClassResolver::class)->resolve($argument, $scope)?->elementModel;

        if ($named !== null) {
            return $named;
        }

        $carried = $rootResult['modelFqcn'] ?? null;

        return $carried !== null && is_a($carried, Model::class, true) && class_basename($carried) === $element ? $carried : null;
    }
}
