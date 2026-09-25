<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Tests\TestCase;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;

uses(TestCase::class)->in(__DIR__);

/**
 * An engine that fails the test if a handler calls back into it, proving the handler resolved or
 * declined without recursing into a sub-expression.
 */
function chainHandlersThrowingEngine(): ExpressionEngine
{
    return new class implements ExpressionEngine
    {
        /** Fails the test: no sub-expression is resolved in this case. */
        public function resolve(Expr $expr): array
        {
            throw new RuntimeException('resolve() must not be called in this case');
        }

        /** Fails the test: no method is spread in this case. */
        public function spreadAnalysis(string $methodName): ?MethodAnalysis
        {
            throw new RuntimeException('spreadAnalysis() must not be called in this case');
        }

        /** Fails the test: no array is analyzed in this case. */
        public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): MethodAnalysis
        {
            throw new RuntimeException('returnArrayAnalysis() must not be called in this case');
        }
    };
}
