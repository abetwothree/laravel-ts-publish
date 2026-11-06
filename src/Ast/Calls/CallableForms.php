<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;

/**
 * The forms a callable value takes where its caller calls it: the call a first-class callable stands for, the closure
 * it amounts to when called with given values, and the first-class callable a variable holds.
 *
 * @internal
 */
final class CallableForms
{
    /** The parameter a callable called with one value holds it in, named as no source can spell a variable. */
    private const string PASSED_VALUE = 'passed value';

    /** A function, class or method name, unqualified. */
    private const string IDENTIFIER = '[A-Za-z_\\x80-\\xff][\\w\\x80-\\xff]*';

    /** A function or class name as a callable string spells one, a leading backslash allowed. */
    private const string NAME = '\\\\?'.self::IDENTIFIER.'(?:\\\\'.self::IDENTIFIER.')*';

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
        return self::isFirstClassCallable($value) ? self::passing($value, 1, $value->getAttributes()) : $value;
    }

    /**
     * The closure a callable amounts to where its caller calls it with $arity values, as array_map() and map() call
     * theirs: a closure as itself; a first-class callable, a function or `Class::method` string, or a `[$object, 'm']`
     * or `[Foo::class, 'm']` pair as `fn ($a, …) => m($a, …)`. Null for any other value.
     */
    public static function asOneValueClosure(Expr $callable, int $arity = 1): ArrowFunction|ClosureExpr|null
    {
        if ($callable instanceof ArrowFunction || $callable instanceof ClosureExpr) {
            return $callable;
        }

        $call = self::isFirstClassCallable($callable) ? $callable : self::spelledCall($callable);

        return $call === null ? null : self::passing($call, $arity, $callable->getAttributes());
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
     * The callable a caller that calls any callable value runs, as Conditionable and transform() do: a closure, a
     * `null` it skips, or a first-class callable, callable string or pair written there or as a local's one write,
     * the last two as the closure asOneValueClosure() makes of them for $arity values. Null for any other spelling.
     */
    public static function calledCallable(Expr $value, AnalysisScope $scope, int $arity = 1): ?Expr
    {
        $span = $value instanceof Variable ? $scope->declaredAt($value) : null;
        $held = match (true) {
            ! $value instanceof Variable => $value,
            $span === null => $scope->soleWriteSeenBy($value),
            default => $span['expr'],
        };

        // A callable a property, a key, a ternary, a call or a parameter holds names no one call, so none is read.
        return match (true) {
            $held instanceof String_, $held instanceof Array_ => self::asOneValueClosure($held, $arity),
            $held instanceof ArrowFunction, $held instanceof ClosureExpr,
            $held instanceof ConstFetch && $held->name->toLowerString() === 'null' => $value,
            $held !== null && self::isFirstClassCallable($held) => $held,
            default => null,
        };
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

    /**
     * The call a closure makes of what each of its $arity parameters holds, each named as no source can spell one.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function passing(FuncCall|MethodCall|StaticCall $call, int $arity, array $attributes): ArrowFunction
    {
        $names = array_map(
            static fn (int $position): string => $position === 0 ? self::PASSED_VALUE : self::PASSED_VALUE.' '.($position + 1),
            range(0, max(1, $arity) - 1),
        );

        $call = clone $call;
        $call->args = array_map(static fn (string $name): Arg => new Arg(new Variable($name)), $names);

        return new ArrowFunction(
            ['params' => array_map(static fn (string $name): Param => new Param(new Variable($name)), $names), 'expr' => $call],
            $attributes,
        );
    }

    /**
     * The call a callable string or `[$receiver, 'method']` pair names; null for any other value.
     */
    private static function spelledCall(Expr $callable): FuncCall|MethodCall|StaticCall|null
    {
        $attributes = $callable->getAttributes();

        if ($callable instanceof String_) {
            if (preg_match('/^('.self::NAME.')::('.self::IDENTIFIER.')$/', $callable->value, $parts) === 1) {
                return new StaticCall(new FullyQualified(ltrim($parts[1], '\\')), new Identifier($parts[2]), [], $attributes);
            }

            return preg_match('/^'.self::NAME.'$/', $callable->value) === 1
                ? new FuncCall(new FullyQualified(ltrim($callable->value, '\\')), [], $attributes)
                : null;
        }

        if (! $callable instanceof Array_ || count($callable->items) !== 2) {
            return null;
        }

        [$receiver, $method] = $callable->items;

        if ($receiver->key !== null || $method->key !== null || $receiver->unpack || $method->unpack || ! $method->value instanceof String_
            || preg_match('/^'.self::IDENTIFIER.'$/', $method->value->value) !== 1
        ) {
            return null;
        }

        $name = new Identifier($method->value->value);
        $class = $receiver->value;

        return match (true) {
            $class instanceof ClassConstFetch && $class->class instanceof Name && $class->name instanceof Identifier
                && $class->name->toLowerString() === 'class' => new StaticCall($class->class, $name, [], $attributes),
            $class instanceof String_ => preg_match('/^'.self::NAME.'$/', $class->value) === 1
                ? new StaticCall(new FullyQualified(ltrim($class->value, '\\')), $name, [], $attributes)
                : null,
            default => new MethodCall($class, $name, [], $attributes),
        };
    }
}
