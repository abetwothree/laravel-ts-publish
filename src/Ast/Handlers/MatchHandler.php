<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
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
final class MatchHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Match_::class];
    }

    /**
     * Union every arm's result, leaving out an arm the engine cannot type as a ternary does (D1).
     *
     * @return ValueExpressionResult|null
     */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr instanceof Match_) {
            return null;
        }

        $results = [];

        foreach ($expr->arms as $arm) {
            // A `throw` arm returns nothing, so it is neither a union member nor an arm the engine failed to type.
            if ($arm->body instanceof Throw_) {
                continue;
            }

            $result = $engine->resolve($arm->body);

            if ($result['type'] === 'unknown') {
                DroppedUnionArms::record($arm->body, $scope, 'match-arm');
            }

            $results[] = $result;
        }

        return $results === [] ? null : ValueResult::unionResults($results);
    }
}
