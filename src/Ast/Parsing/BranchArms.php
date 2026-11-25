<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\MatchArm;

/**
 * The values an expression that picks one of several may give: a ternary's arms and a `match`'s arm bodies.
 *
 * @internal
 */
final class BranchArms
{
    /**
     * A ternary's two arms, a `?:`'s condition standing as its truthy arm, or a `match`'s arm bodies in order; null for
     * any other expression.
     *
     * @return list<Expr>|null
     */
    public static function of(Expr $expr): ?array
    {
        return match (true) {
            $expr instanceof Ternary => [$expr->if ?? $expr->cond, $expr->else],
            $expr instanceof Match_ => array_values(array_map(static fn (MatchArm $arm): Expr => $arm->body, $expr->arms)),
            default => null,
        };
    }
}
