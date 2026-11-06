<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Members\PropertyWriteSites;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReturnLiteralReader;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use Throwable;

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
     * The callables a caller that calls any callable runs, as Conditionable and transform() do: a value that may hold
     * only closures, first-class callables or `null` as heldCallable() reads it, else one closure per callable string,
     * pair or closure it may hold, for $arity values. Null where a value it may hold names no call.
     *
     * @return non-empty-list<Expr>|null
     */
    public static function calledCallables(Expr $value, AnalysisScope $scope, int $arity = 1): ?array
    {
        $held = self::heldValues($value, $scope, []);

        if ($held === null) {
            return null;
        }

        if (! array_any($held, static fn (Expr $one): bool => $one instanceof String_ || $one instanceof Array_)) {
            return [self::heldCallable($value, $scope)];
        }

        $called = [];

        foreach ($held as $one) {
            $closure = $one instanceof String_ || $one instanceof Array_ ? self::asOneValueClosure($one, $arity) : $one;

            if ($closure === null) {
                return null;
            }

            $called[] = $closure;
        }

        return $called;
    }

    /**
     * Whether the expression is a first-class callable: PHP allows one only on a function, method or static call.
     *
     * @phpstan-assert-if-true =FuncCall|MethodCall|StaticCall $expr
     */
    public static function isFirstClassCallable(Expr $expr): bool
    {
        return ($expr instanceof FuncCall || $expr instanceof MethodCall || $expr instanceof StaticCall)
            && $expr->isFirstClassCallable();
    }

    /**
     * The callable values an expression may hold, a closure, a first-class callable, `null`, a string or an array: via
     * `?:`, `??`, `match`, a local's writes or literal key, a `$this` property, a method's literal return, a constant.
     * Null where one may hold anything else, or a cycle of locals.
     *
     * @param  array<string, true>  $followed  the locals already followed
     * @return non-empty-list<Expr>|null
     */
    private static function heldValues(Expr $value, AnalysisScope $scope, array $followed): ?array
    {
        if ($value instanceof ArrowFunction || $value instanceof ClosureExpr || $value instanceof String_
            || $value instanceof Array_ || self::isFirstClassCallable($value) || self::isNull($value)
        ) {
            return [$value];
        }

        $parts = match (true) {
            $value instanceof Ternary => [$value->if ?? $value->cond, $value->else],
            $value instanceof Coalesce => [$value->left, $value->right],
            $value instanceof Match_ => array_map(static fn (MatchArm $arm): Expr => $arm->body, $value->arms),
            $value instanceof Variable => self::localValues($value, $scope, $followed),
            $value instanceof ArrayDimFetch => self::keyedValues($value, $scope, $followed),
            $value instanceof PropertyFetch => self::propertyValues($value, $scope),
            $value instanceof MethodCall, $value instanceof StaticCall => self::returnedLiteral($value, $scope),
            default => self::constantValue($value, $scope),
        };

        if ($parts === null || $parts === []) {
            return null;
        }

        $held = [];

        foreach ($parts as $part) {
            $values = self::heldValues($part, $scope, $value instanceof Variable && is_string($value->name)
                ? [...$followed, $value->name => true]
                : $followed);

            if ($values === null) {
                return null;
            }

            array_push($held, ...$values);
        }

        return $held;
    }

    /**
     * The writes a read of a local sees, its inline `@var` span's value first; null for a parameter, or one followed.
     *
     * @param  array<string, true>  $followed
     * @return list<Expr>|null
     */
    private static function localValues(Variable $read, AnalysisScope $scope, array $followed): ?array
    {
        if (! is_string($read->name) || isset($followed[$read->name])) {
            return null;
        }

        $span = $scope->declaredAt($read);

        return $span === null ? $scope->writesSeenBy($read) : [$span['expr']];
    }

    /**
     * The values a literal key names in each array literal a local may hold; null where one holds no such item.
     *
     * @param  array<string, true>  $followed
     * @return list<Expr>|null
     */
    private static function keyedValues(ArrayDimFetch $fetch, AnalysisScope $scope, array $followed): ?array
    {
        $key = $fetch->dim instanceof String_ || $fetch->dim instanceof Int_ ? $fetch->dim->value : null;
        $arrays = $key === null ? null : self::heldValues($fetch->var, $scope, $followed);
        $items = [];

        foreach ($arrays ?? [] as $array) {
            $item = $array instanceof Array_ ? array_find(
                $array->items,
                static fn (ArrayItem $item): bool => ($item->key instanceof String_ || $item->key instanceof Int_)
                    && $item->key->value === $key,
            ) : null;

            if ($item === null) {
                return null;
            }

            $items[] = $item->value;
        }

        return $arrays === null ? null : $items;
    }

    /**
     * A `$this` property's default and every value the class assigns it; null where a write is no plain assignment of
     * a callable that reads no other scope's local, or the property is promoted, magic or holds a default no callable
     * spells.
     *
     * @return list<Expr>|null
     */
    private static function propertyValues(PropertyFetch $fetch, AnalysisScope $scope): ?array
    {
        if (! self::isThis($fetch->var) || ! $fetch->name instanceof Identifier) {
            return null;
        }

        $name = $fetch->name->toString();
        $class = $scope->thisMemberClass($name, false);
        $property = $class->hasProperty($name) ? $class->getProperty($name) : null;
        $writes = $property === null || $property->isPromoted() ? null : resolve(PropertyWriteSites::class)->of($class, $name);

        if ($property === null || $writes === null) {
            return null;
        }

        $values = [];

        foreach ($writes as [$node]) {
            if (! $node instanceof Assign || ! self::readsNoLocal($node->expr)) {
                return null;
            }

            $values[] = $node->expr;
        }

        $default = $property->hasDefaultValue() || ! $property->hasType() ? $property->getDefaultValue() : false;
        $spelled = $default === false ? [] : self::spelledValue($default, $fetch->getAttributes());

        return $spelled === null ? null : [...$spelled, ...$values];
    }

    /**
     * The literal every return of a `$this->m()`, `self::m()` or `static::m()` call spells, a string or a list of two;
     * null for any other call.
     *
     * @return list<Expr>|null
     */
    private static function returnedLiteral(MethodCall|StaticCall $call, AnalysisScope $scope): ?array
    {
        if (! $call->name instanceof Identifier) {
            return null;
        }

        $method = $call->name->toString();
        $class = match (true) {
            $call instanceof MethodCall => self::isThis($call->var) ? $scope->thisMemberClass($method, true) : null,
            ! $call->class instanceof Name => null,
            $call->class->toLowerString() === 'self' => $scope->declaringClass,
            $call->class->toLowerString() === 'static' => $scope->thisMemberClass($method, true),
            default => null,
        };

        if ($class === null || ! $class->hasMethod($method)) {
            return null;
        }

        $reader = resolve(ReturnLiteralReader::class);
        $literal = $reader->stringLiteral($class->getName(), $method) ?? $reader->strings($class->getName(), $method);

        return $literal === null ? null : self::spelledValue($literal, $call->getAttributes());
    }

    /**
     * The value a constant expression evaluates to, as a class or global constant or literals PHP folds; null where it
     * is no constant expression.
     *
     * @return list<Expr>|null
     */
    private static function constantValue(Expr $value, AnalysisScope $scope): ?array
    {
        try {
            $constant = resolve(ValueResolver::class)->evaluateConstantExpression($value, $scope);
        } catch (Throwable) {
            // A constant's initializer runs only as it is read, and may name a constant no class declares.
            return null;
        }

        return self::spelledValue($constant, $value->getAttributes());
    }

    /**
     * A value that spells a callable string, a pair of strings or `null`, as the expression a source would write; null
     * for any other.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<Expr>|null
     */
    private static function spelledValue(mixed $value, array $attributes): ?array
    {
        if (is_array($value) && array_is_list($value) && count($value) === 2
            && is_string($value[0]) && is_string($value[1])
        ) {
            return [new Array_([
                new ArrayItem(new String_($value[0], $attributes)),
                new ArrayItem(new String_($value[1], $attributes)),
            ], $attributes)];
        }

        return match (true) {
            $value === null => [new ConstFetch(new Name('null'), $attributes)],
            is_string($value) => [new String_($value, $attributes)],
            default => null,
        };
    }

    /**
     * Whether a callable another method writes reads the same wherever it is read: a string, `null`, a pair of a class
     * name or `$this` and a method name, a first-class callable on `$this` or a class, or a closure importing nothing.
     */
    private static function readsNoLocal(Expr $value): bool
    {
        $isNamed = static fn (Expr $expr): bool => $expr instanceof String_ || $expr instanceof ClassConstFetch || self::isThis($expr);

        return match (true) {
            $value instanceof String_, self::isNull($value) => true,
            $value instanceof ClosureExpr => $value->uses === [],
            $value instanceof Array_ => $value->items !== []
                && array_all($value->items, static fn (ArrayItem $item): bool => $item->key === null && $isNamed($item->value)),
            $value instanceof MethodCall => $value->isFirstClassCallable() && self::isThis($value->var),
            default => $value instanceof StaticCall && $value->isFirstClassCallable(),
        };
    }

    /**
     * Whether an expression is `$this`.
     */
    private static function isThis(Expr $expr): bool
    {
        return $expr instanceof Variable && $expr->name === 'this';
    }

    /**
     * Whether an expression is the literal `null`.
     */
    private static function isNull(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && $expr->name->toLowerString() === 'null';
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
