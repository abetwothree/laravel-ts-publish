<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Contracts;

use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;

/**
 * Full expression resolution, for handlers to call back into on sub-expressions they don't own.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
interface ExpressionEngine
{
    /**
     * Resolve an expression to its TypeScript type by dispatching to the first registered
     * ExpressionHandler that claims it; an unclaimed expression degrades to `unknown`.
     *
     * @return ValueExpressionResult
     */
    public function resolve(Expr $expr): array;

    /**
     * Spread-analyze a named method on the subject under analysis, for a handler that resolves a
     * self-returning chain onto a non-preserving method body instead of degrading to `unknown`.
     */
    public function spreadAnalysis(string $methodName): ?MethodAnalysis;

    /**
     * Analyze an array literal's items into properties, spreads, and FQCN maps — the same
     * return-position array machinery a resource's own toArray() return uses. $topLevel means
     * "this literal IS the whole return shape" (Inertia props, a delegated return), not AST depth.
     */
    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis;
}
