<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Operators;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\KeepsHeldObjects;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Throw_;

/**
 * A `match` expression, typed as the union of its arms' results.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class MatchHandler implements ExpressionHandler, KeepsHeldObjects
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
            // A `throw` arm returns nothing, so it is neither a union member nor an arm the engine failed to type.
            if ($arm->body instanceof Throw_) {
                continue;
            }

            $result = $engine->resolve($arm->body);

            if ($result['type'] === 'unknown') {
                $left[] = [$arm->body, 'match-arm'];
            }

            $results[] = $result;
        }

        if ($results === []) {
            return null;
        }

        $union = DroppedUnionArms::leftOut(ValueResult::unionResults($results), $left, $scope);

        return ValueResult::withEnumArmShapes($union, $results);
    }
}
