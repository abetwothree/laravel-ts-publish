<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Array_ as CastArray_;
use PhpParser\Node\Expr\Cast\Bool_ as CastBool;
use PhpParser\Node\Expr\Cast\Double as CastDouble;
use PhpParser\Node\Expr\Cast\Int_ as CastInt;
use PhpParser\Node\Expr\Cast\String_ as CastString;

/**
 * PHP cast operators — the cast alone determines the type, not the inner expression.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class CastHandler implements ExpressionHandler
{
    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Cast::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof CastBool) {
            return ['type' => 'boolean', 'optional' => false];
        }

        if ($expr instanceof CastInt || $expr instanceof CastDouble) {
            return ['type' => 'number', 'optional' => false];
        }

        if ($expr instanceof CastString) {
            return ['type' => 'string', 'optional' => false];
        }

        if ($expr instanceof CastArray_) {
            // A cast of an array literal is the identity — the shape survives. Any other operand
            // (e.g. a scalar, which PHP wraps into a single-element list) stays the flat fallback.
            if ($expr->expr instanceof Array_) {
                return $engine->resolve($expr->expr);
            }

            return ['type' => 'unknown[]', 'optional' => false];
        }

        // (object)/(unset) casts: the legacy chain never matched these, so decline to preserve fall-through.
        return null;
    }
}
