<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;

/**
 * The forms a callable value takes where its caller calls it: the call a first-class callable stands for, the closure
 * it amounts to when called with one value, and the first-class callable a variable holds.
 *
 * @internal
 */
final class CallableForms
{
    /** The parameter a callable called with one value holds it in, named as no source can spell a variable. */
    private const string PASSED_VALUE = 'passed value';

    /**
     * The call a first-class callable stands for, with its `...` dropped; any other expression unchanged.
     */
    public static function invokedCall(Expr $value): Expr
    {
        if (! self::isFirstClassCallable($value)) {
            return $value;
        }

        $call = clone $value;
        $call->args = [];

        return $call;
    }

    /**
     * The closure a first-class callable amounts to where Laravel calls it with one value, `fn ($v) => m($v)`, so a
     * writer binds that value as it binds a closure's first parameter; any other expression unchanged.
     */
    public static function passingOneValue(Expr $value): Expr
    {
        if (! self::isFirstClassCallable($value)) {
            return $value;
        }

        $call = clone $value;
        $call->args = [new Arg(new Variable(self::PASSED_VALUE))];

        return new ArrowFunction(
            ['params' => [new Param(new Variable(self::PASSED_VALUE))], 'expr' => $call],
            $value->getAttributes(),
        );
    }

    /**
     * The first-class callable a variable holds where it is read, so a caller that calls its value reads the call.
     *
     * The write an inline `@var` spans, or the one write the read sees. Any other expression comes back unchanged, as
     * does a read that sees a join of writes or a parameter's own binding.
     */
    public static function heldCallable(Expr $value, AnalysisScope $scope): Expr
    {
        if (! $value instanceof Variable) {
            return $value;
        }

        $span = $scope->declaredAt($value);
        $held = $span === null ? $scope->soleWriteSeenBy($value) : $span['expr'];

        return $held !== null && self::isFirstClassCallable($held) ? $held : $value;
    }

    /**
     * Whether the expression is a first-class callable: PHP allows one only on a function, method or static call.
     *
     * @phpstan-assert-if-true FuncCall|MethodCall|StaticCall $expr
     */
    public static function isFirstClassCallable(Expr $expr): bool
    {
        return ($expr instanceof FuncCall || $expr instanceof MethodCall || $expr instanceof StaticCall)
            && $expr->isFirstClassCallable();
    }
}
