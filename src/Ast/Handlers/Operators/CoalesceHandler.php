<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;

/**
 * Analyze a null-coalescing expression (`$left ?? $right`).
 *
 * Doesn't delegate to ValueResult::analyzeClosureUnion(): the left operand's `null` never reaches the result, so its
 * arm is unioned with that `null` stripped, unless the call of a closure or Inertia prop `??` returns whole brings it.
 * A left holding a collection is returned whole. Only operands contributing a result member get their channels merged.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CoalesceHandler implements ExpressionHandler, KeepsHeldObjects
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [BinaryOp\Coalesce::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof BinaryOp\Coalesce) {
            $leftResult = ValueResult::checkedOperand($engine->resolve($expr->left));

            // A collection is an object, never null, so `??` hands it back whole and never runs its right operand.
            if ($this->holdsCollection($expr->left, $leftResult, $scope)) {
                return $leftResult;
            }

            // An object is never null, so `??` returns it whole and Inertia sends the null its call returns. Inertia
            // sends a callable a called closure returns as `{}`, which no type read here admits, so it reads as before.
            $held = $leftResult['type'] === 'unknown' || $engine->reading()->encodes()
                || ($scope->readsCallableReturn && ! ($leftResult['heldProp'] ?? false))
                ? null
                : ValueResult::heldNullsOf($leftResult);

            if ($held !== null && ! $held['plainNull']) {
                return $leftResult;
            }

            $rightResult = $engine->resolve($expr->right);
            // A MissingValue is not null, so `??` returns it; the right operand is reached only past a left null.
            $optional = $leftResult['optional']
                || ($rightResult['optional'] && ($leftResult['type'] === 'unknown' || TsTypeString::hasNullArm($leftResult['type'])));

            // With a non-null fallback only an object's call can still return null, so any other left null goes.
            if ($held === null || ! $held['objectNull']) {
                $leftResult['type'] = TsTypeString::withoutNullArm($leftResult['type']);
            }

            // Only the plain null is replaced, so the left's plain arms past `??` are never null.
            if ($held !== null) {
                $leftResult['mayHoldObject'] = [...$held, 'plainNull' => false];
            }

            // unionResults() leaves out an operand the engine could not type, so only the audit is told which one:
            // the left one alone when neither has a type.
            $dropped = [];

            if ($leftResult['type'] === 'unknown') {
                DroppedUnionArms::record($expr->left, $scope, 'coalesce-left');
                $dropped[] = $expr->left;
            } elseif ($rightResult['type'] === 'unknown') {
                DroppedUnionArms::record($expr->right, $scope, 'coalesce-right');
                $dropped[] = $expr->right;
            }

            // Two arms that render alike are one member, unless they spell one name for two classes, which
            // unionResults() keeps apart.
            $union = ValueResult::unionResults([$leftResult, $rightResult]);

            return [...DroppedUnionArms::warnOfPlaceholder($union, $dropped, $scope), 'optional' => $optional];
        }

        return null;
    }

    /**
     * Whether an operand holds a collection on every path: a type with no `null` arm, which the receiver resolver
     * reads as a collection that no `?->` can end as null.
     *
     * @param  ValueExpressionResult  $result
     */
    private function holdsCollection(Expr $operand, array $result, AnalysisScope $scope): bool
    {
        if ($result['type'] === 'unknown' || TsTypeString::hasNullArm($result['type'])) {
            return false;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($operand, $scope);

        return $receiver !== null && ! $receiver->shortCircuits && $receiver->within([Enumerable::class]);
    }
}
