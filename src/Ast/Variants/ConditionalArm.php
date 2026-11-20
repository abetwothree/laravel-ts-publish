<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use PhpParser\Node\Expr;

/**
 * One value a conditional expression can take, beside the guard that selects it.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final readonly class ConditionalArm
{
    /**
     * An arm by its guard, its expression and its result.
     *
     * @param  Expr|null  $guard  null where the arm is taken whenever no arm before it was
     * @param  Expr|null  $body  the expression the arm evaluates, which may split again
     * @param  ValueExpressionResult|null  $result  null where the arm sends no value: the key is removed, or it throws
     * @param  bool  $throws  whether PHP throws where this arm is taken, so it sends nothing
     */
    public function __construct(
        public ?Expr $guard,
        public ?Expr $body,
        public ?array $result,
        public bool $throws = false,
    ) {}
}
