<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Collections;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Callbacks\BindsMapCallbacks;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Collections\ReadsCollectedItems;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\CollectionSteps;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use ReflectionMethod;

/**
 * A collection chain rooted at `collect($arg)`: a list's element type survives each step that keeps the items, a
 * leading `values()` lists a Record's or a shape's values, `keys()` lists the keys, and at most one `map()` with any
 * callable maps any array `$arg` holds, keeping its keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CollectionPipelineHandler implements ExpressionHandler
{
    use BindsMapCallbacks;
    use ReadsCollectedItems;

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

        $convertsItems = CollectionSteps::endsInMappedToArray($steps);
        $arrayed = $convertsItems || $steps[count($steps) - 1]['name'] === 'all';

        if ($convertsItems) {
            array_pop($steps);
        }
        // A leading values() lists a Record's or a shape's values.
        $listsValues = ! $this->isList($rootResult['type']) && $steps[0]['name'] === 'values';

        if ($listsValues) {
            array_shift($steps);
            $rootResult = $this->valuesListed($rootResult);
        }

        // Only a list takes the steps that keep the items; any other root keeps its keys through `all()` and `map()`.
        $walk = $rootResult !== null && ($this->isList($rootResult['type']) || ($steps[0]['name'] ?? null) === 'keys'
            || array_all($steps, static fn (array $step): bool => in_array($step['name'], ['all', 'map'], true)))
            ? CollectionSteps::walk($steps, false)
            : null;

        // keys() lists the keys, which carry none of the items' channels: the root's own where it names each one.
        if ($walk !== null && $walk['keys']) {
            $rootResult = $this->collectedKeys($listsValues ? null : $items, $rootResult, $scope);
        }

        if ($walk === null || $rootResult === null) {
            return null;
        }

        $listed = $this->isList($rootResult['type']);
        $keys = $walk['keys'] ? $rootResult : null;
        $result = $walk['map'] === null ? $rootResult : $this->mappedItems(
            $walk['map'],
            $rootResult,
            // A closure: an arrow function would make PHPStan take the first read's non-null result for the re-read.
            function () use ($items, $scope, $engine, $listsValues, $keys): ?array {
                $reread = $keys ?? $this->collectedItems($items, $scope, $engine);

                return $keys === null && $listsValues ? $this->valuesListed($reread) : $reread;
            },
            $arrayed,
            $walk['keys'] ? null : $this->elementModel($items, $scope),
            $scope,
            $engine,
        );

        if ($result !== null && $convertsItems && $this->convertsMappedItems($result)) {
            return null;
        }

        if ($result === null || $listed) {
            return $result !== null && $walk['keyed'] ? [...$result, 'type' => ArrayArms::keyedList($result['type'])] : $result;
        }

        // A kept Collection's keys reach json_encode() as they are, since a resource's filter never enters one; the
        // array all() hands back is re-indexed as any other.
        if (! $arrayed) {
            return [...$result, 'keepsKeys' => true];
        }

        unset($result['keepsKeys']);

        return $result;
    }

    /**
     * The values a leading `values()` lists a Record's or a shape's items as, keyed 0..n-1.
     *
     * @param  ValueExpressionResult|null  $items
     * @return ValueExpressionResult|null
     */
    private function valuesListed(?array $items): ?array
    {
        $type = $items === null ? null : ArrayArms::valuesList($items['type']);

        if ($items === null || $type === null) {
            return null;
        }

        unset($items['keepsKeys'], $items['unnamedKeys']);

        return [...$items, 'type' => $type, 'optional' => false, 'listKeys' => true];
    }

    /**
     * What one `map()` makes of a root's items, read again with each enum case as its enum where the first read leaves
     * its callback unread.
     *
     * @param  ValueExpressionResult  $items
     * @param  Closure(): (ValueExpressionResult|null)  $read
     * @param  class-string<Model>|null  $elementModel
     * @return ValueExpressionResult|null
     */
    private function mappedItems(
        MethodCall $map,
        array $items,
        Closure $read,
        bool $arrayed,
        ?string $elementModel,
        AnalysisScope $scope,
        ExpressionEngine $engine,
    ): ?array {
        $callback = CallArguments::for($map, new ReflectionMethod(Collection::class, 'map'))->named('callback')?->value;

        return $callback === null ? null : $this->mappedReadingCases(
            $items,
            $read,
            fn (array $root): ?array => $this->mappedArrayResult($callback, $root, $arrayed, $scope, $engine, $elementModel),
            $scope,
        );
    }

    /**
     * Whether a root's items are one `T[]` list, whose elements the walk keeps; never an `Array<T>`.
     */
    private function isList(string $type): bool
    {
        return str_ends_with($type, '[]') && ArrayArms::listElement($type) !== null;
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
