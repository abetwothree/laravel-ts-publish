<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\MatchArm;

/**
 * Whether a value may hold a callable, which Laravel sends as the Closure object, `{}`, unless its caller calls it.
 *
 * @internal
 */
final class HeldCallables
{
    /**
     * Whether a value may be a closure, an arrow function or, where counted, a first-class callable.
     *
     * As itself, an arm of `?:`, `??` or `match`, or a local's one top-level write, which is how the engine reads that
     * local. A variable the scope cannot follow there may hold one only where $unboundMayHold says so, and never one
     * holding a model, a collection or a request.
     */
    public static function mayHold(
        Expr $expr,
        AnalysisScope $scope,
        bool $firstClassCallables = true,
        bool $unboundMayHold = false,
    ): bool {
        return self::follows($expr, $scope, $firstClassCallables, $unboundMayHold, []);
    }

    /**
     * mayHold() over the locals already followed, so a cycle of copies reads as a variable it cannot follow.
     *
     * @param  array<string, true>  $followed
     */
    private static function follows(
        Expr $expr,
        AnalysisScope $scope,
        bool $firstClassCallables,
        bool $unboundMayHold,
        array $followed,
    ): bool {
        if ($expr instanceof ClosureExpr || $expr instanceof ArrowFunction) {
            return true;
        }

        if ($expr instanceof CallLike) {
            return $firstClassCallables && $expr->isFirstClassCallable();
        }

        if ($expr instanceof Variable) {
            $name = is_string($expr->name) ? $expr->name : null;
            $bound = $name === null || isset($followed[$name]) ? null : $scope->localVarBindings[$name] ?? null;

            if ($bound !== null) {
                return self::follows($bound, $scope, $firstClassCallables, $unboundMayHold, [...$followed, $name => true]);
            }

            return $unboundMayHold && ($name === null || (! isset($scope->varModelBindings[$name])
                && ! isset($scope->varCollectionBindings[$name])
                && ! isset($scope->requestVarNames[$name])));
        }

        $arms = match (true) {
            $expr instanceof Ternary => [$expr->if ?? $expr->cond, $expr->else],
            $expr instanceof Coalesce => [$expr->left, $expr->right],
            $expr instanceof Match_ => array_map(fn (MatchArm $arm): Expr => $arm->body, $expr->arms),
            default => [],
        };

        return array_any(
            $arms,
            fn (Expr $arm): bool => self::follows($arm, $scope, $firstClassCallables, $unboundMayHold, $followed),
        );
    }
}
