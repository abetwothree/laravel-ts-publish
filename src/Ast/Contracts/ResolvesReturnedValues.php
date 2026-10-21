<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * An engine that reads a body's returned variable through its writes, as a method's own returned variable is read.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
interface ResolvesReturnedValues
{
    /**
     * Resolve what a body returns, a returned variable the scope cannot type read through the walk over its writes.
     *
     * @param  array<Stmt>  $stmts  the body that returns $returned
     * @return ValueExpressionResult
     */
    public function resolveReturned(Expr $returned, array $stmts): array;
}
