<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;

/**
 * An engine that reads a local a body builds through the walk over its writes, as a method's own returned variable is
 * read: one the body returns, or one a value reads.
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

    /**
     * The array a local holds where it is read, from its writes in the body's statements that end before the read; null
     * where the walk does not read them completely.
     *
     * @param  array<Stmt>  $stmts  the body holding the read
     * @return ValueExpressionResult|null
     */
    public function walkedLocal(Variable $read, array $stmts): ?array;
}
