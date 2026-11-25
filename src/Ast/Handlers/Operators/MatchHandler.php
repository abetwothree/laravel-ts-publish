<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\SplitsOnCondition;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArm;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionalArms;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\MatchArm;

/**
 * A `match` expression, typed as the union of its arms' results.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class MatchHandler implements ExpressionHandler, KeepsHeldObjects, SplitsOnCondition
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Match_::class];
    }

    /**
     * Union every arm's result, leaving out an arm the engine cannot type, as a ternary does.
     *
     * @return ValueExpressionResult|null
     */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof Match_) {
            return null;
        }

        $results = [];
        $left = [];

        foreach ($expr->arms as $arm) {
            $result = $this->armResult($arm, $engine);

            // A `throw` arm returns nothing, so it is neither a union member nor an arm the engine failed to type.
            if ($result === null) {
                continue;
            }

            if ($result['type'] === 'unknown') {
                $left[] = [$arm->body, 'match-arm'];
            }

            $results[] = $result;
        }

        if ($results === []) {
            return null;
        }

        return DroppedUnionArms::leftOut(ValueResult::unionResults($results), $left, $scope);
    }

    /**
     * Each arm guarded by its subject being identical to one of its conditions, with `default` last, as PHP tries it
     * once no other arm matches; with no `default`, a last arm throws, as PHP does for a value no arm names.
     */
    public function conditionalArms(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?ConditionalArms
    {
        if (! $expr instanceof Match_) {
            return null;
        }

        $arms = [];
        $default = new ConditionalArm(null, null, null, throws: true);

        foreach ($expr->arms as $arm) {
            $result = $this->armResult($arm, $engine);
            $guard = $this->armGuard($expr->cond, $arm);
            $conditional = new ConditionalArm($guard, $arm->body, $result, throws: $result === null);

            if ($guard === null) {
                $default = $conditional;
            } else {
                $arms[] = $conditional;
            }
        }

        return new ConditionalArms([...$arms, $default]);
    }

    /**
     * One arm's result, or null for a `throw`, which returns nothing.
     *
     * @return ValueExpressionResult|null
     */
    private function armResult(MatchArm $arm, ExpressionEngine $engine): ?array
    {
        return $arm->body instanceof Throw_ ? null : $engine->resolve($arm->body);
    }

    /**
     * The guard that selects an arm: its subject identical to any of its conditions, as `match` compares them; none for
     * `default`.
     */
    private function armGuard(Expr $subject, MatchArm $arm): ?Expr
    {
        $guard = null;

        foreach ($arm->conds ?? [] as $cond) {
            $test = new Identical($subject, $cond);
            $guard = $guard === null ? $test : new BooleanOr($guard, $test);
        }

        return $guard;
    }
}
