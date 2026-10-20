<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;

/**
 * A first-class callable, such as `strlen(...)` or `$this->label(...)`, is a Closure, never a call: json_encode()
 * writes it as `{}`. Where the caller invokes every callable it holds, as Inertia does, it types as that call.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class FirstClassCallableHandler implements ExpressionHandler
{
    /** The parameter a callable called with one value holds it in, named as no source can spell a variable. */
    private const string PASSED_VALUE = 'passed value';

    /** Whether the subject's caller calls each callable value before it encodes it, as Inertia does. */
    public function __construct(private readonly bool $invoked = false) {}

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
     * Whether this handler reads a first-class callable as the call it stands for, as the caller invokes it.
     */
    public function invokesCallables(): bool
    {
        return $this->invoked;
    }

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [FuncCall::class, MethodCall::class, StaticCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! self::isFirstClassCallable($expr)) {
            return null;
        }

        if ($this->invoked) {
            return $engine->resolve(self::invokedCall($expr));
        }

        $this->warnOfUncalledCallable($expr, $scope);

        return ['type' => TsTypeString::EMPTY_OBJECT, 'optional' => false];
    }

    /**
     * Whether the expression is a first-class callable: PHP allows one only on a function, method or static call.
     *
     * @phpstan-assert-if-true FuncCall|MethodCall|StaticCall $expr
     */
    private static function isFirstClassCallable(Expr $expr): bool
    {
        return ($expr instanceof FuncCall || $expr instanceof MethodCall || $expr instanceof StaticCall)
            && $expr->isFirstClassCallable();
    }

    /**
     * Warn that a first-class callable creates a Closure where nothing calls it, so a call was almost certainly meant.
     */
    private function warnOfUncalledCallable(Expr $expr, AnalysisScope $scope): void
    {
        AnalysisWarnings::addOnce($scope->subjectReflection->getName(), sprintf(
            'A first-class callable on line %d creates a Closure instead of calling the method; nothing in this '
            .'position calls it. Call the method instead.',
            $expr->getStartLine(),
        ));
    }
}
