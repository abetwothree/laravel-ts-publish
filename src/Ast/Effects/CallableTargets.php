<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionMethod;

/**
 * The method of `$this`, or of a named class, that a call or a callable value runs, read in a body whose `self` is a
 * declaring class and whose `$this` is an instance of the runtime class.
 *
 * @phpstan-type CallableTarget = array{ReflectionClass<object>, string}
 *
 * @internal
 */
final readonly class CallableTargets
{
    /** A class's name and one of its methods, as `'X::m'` spells them. */
    private const string NAMED_METHOD = '/^\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)::([A-Za-z_][A-Za-z0-9_]*)$/';

    /**
     * Targets read for a `$this` that is an instance of $runtime.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    public function __construct(private ReflectionClass $runtime) {}

    /**
     * Whether an expression is `$this`.
     */
    public static function isThis(Node $node): bool
    {
        return $node instanceof Variable && $node->name === 'this';
    }

    /**
     * The class and method a callable value names: a first-class callable of a method run on `$this` or of another
     * class's static method, `[$this, 'm']`, `'static::m'` and its kin, or a class's name and a method, as
     * `[X::class, 'm']`, `'X::m'` or `X::class.'::m'`; false where it names one by a name the source does not spell or
     * no class here declares; null where it names no method.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    public function callableTarget(Expr $callable, ReflectionClass $declaring): array|false|null
    {
        $relative = ReadOnlyCalls::relativeCallable($callable);

        if ($relative !== null) {
            return $this->namedTarget(new Name($relative[0]), $relative[1], $declaring);
        }

        return match (true) {
            $callable instanceof Array_ => $this->arrayTarget($callable, $declaring),
            $callable instanceof String_ => preg_match(self::NAMED_METHOD, $callable->value, $parts) === 1
                ? $this->namedTarget(new Name($parts[1]), $parts[2], $declaring)
                : null,
            $callable instanceof Concat => $this->concatTarget($callable, $declaring),
            $callable instanceof CallLike => $this->firstClassTarget($callable, $declaring),
            default => null,
        };
    }

    /**
     * The class and name of the method a call runs on `$this`: through `$this->`, `$this::`, `self::`, `static::` or
     * `parent::`, or an ancestor's name for a method PHP runs on `$this`; null for any other call.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|null
     */
    public function thisMethod(CallLike $call, ReflectionClass $declaring): ?array
    {
        if (! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)
            || ! $call->name instanceof Identifier
        ) {
            return null;
        }

        $method = $call->name->toString();
        $class = match (true) {
            $call instanceof StaticCall => $this->staticCallClass($call, $method, $declaring),
            self::isThis($call->var) => AnalysisScope::memberClassOf($declaring, $this->runtime, $method, true),
            default => null,
        };

        return $class === null ? null : [$class, $method];
    }

    /**
     * The parts a chain of concatenations joins, in order.
     *
     * @return list<Expr>
     */
    private static function concatParts(Expr $expr): array
    {
        return $expr instanceof Concat
            ? [...self::concatParts($expr->left), ...self::concatParts($expr->right)]
            : [$expr];
    }

    /**
     * The class a callable array's first item names: `X::class`, or the name of a class here declared.
     */
    private static function arrayClass(Expr $holder): ?Name
    {
        if ($holder instanceof ClassConstFetch && ReadOnlyCalls::isClassName($holder)
            && $holder->class instanceof Name
        ) {
            return $holder->class;
        }

        return $holder instanceof String_ && class_exists($holder->value) ? new Name($holder->value) : null;
    }

    /**
     * The method `[$this, 'm']` names, or `[X::class, 'm']` and `['X', 'm']` name of a class; null for any other array.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    private function arrayTarget(Array_ $callable, ReflectionClass $declaring): array|false|null
    {
        if (count($callable->items) !== 2) {
            return null;
        }

        [$holder, $name] = [$callable->items[0]->value, $callable->items[1]->value];
        $method = $name instanceof String_ ? $name->value : null;
        $class = self::arrayClass($holder);

        return match (true) {
            self::isThis($holder) => $method === null
                ? false
                : [AnalysisScope::memberClassOf($declaring, $this->runtime, $method, true), $method],
            $class !== null => $this->namedTarget($class, $method, $declaring),
            default => null,
        };
    }

    /**
     * The method `X::class.'::m'` names; null for a concatenation that does not open with a class's name and `::`.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    private function concatTarget(Concat $callable, ReflectionClass $declaring): array|false|null
    {
        [$class, $first] = self::concatParts($callable);
        $rest = array_slice(self::concatParts($callable), 1);

        if (! $class instanceof ClassConstFetch || ! ReadOnlyCalls::isClassName($class)
            || ! $class->class instanceof Name || ! $first instanceof String_ || ! str_starts_with($first->value, '::')
        ) {
            return null;
        }

        $spelled = array_filter($rest, static fn (Expr $part): bool => $part instanceof String_);
        $method = count($spelled) === count($rest)
            ? substr(implode('', array_map(static fn (String_ $part): string => $part->value, $spelled)), 2)
            : '';

        return $this->namedTarget($class->class, $method === '' ? null : $method, $declaring);
    }

    /**
     * The method a first-class callable runs on `$this`, or of another class it names; null for a function's or one
     * bound to another object.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    private function firstClassTarget(CallLike $callable, ReflectionClass $declaring): array|false|null
    {
        if (! $callable->isFirstClassCallable() || $callable instanceof FuncCall
            || (($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && ! self::isThis($callable->var))
        ) {
            return null;
        }

        $onThis = $this->thisMethod($callable, $declaring);

        if ($onThis !== null || ! $callable instanceof StaticCall || ! $callable->class instanceof Name
            || ! $callable->name instanceof Identifier
        ) {
            return $onThis ?? false;
        }

        return $this->namedTarget($callable->class, $callable->name->toString(), $declaring);
    }

    /**
     * The class a callable names by a class's name, beside its method: `self`, `static` and `parent` as the declaring
     * class names them, `$this`'s class or an ancestor, which runs a method it lacks through `__call()`, or another
     * class declaring the method; false where the source spells no method or names no such class.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false
     */
    private function namedTarget(Name $class, ?string $method, ReflectionClass $declaring): array|false
    {
        $named = $class->toString();
        $target = $class->isSpecialClassName()
            ? AnalysisScope::relativeClassOf($class, $declaring, $this->runtime)
            : null;

        if ($method !== null && ! $class->isSpecialClassName() && class_exists($named)
            && (is_a($this->runtime->getName(), $named, true) || method_exists($named, $method))
        ) {
            $target = new ReflectionClass($named);
        }

        return $target === null || $method === null ? false : [$target, $method];
    }

    /**
     * The class a static-syntax call runs a method of on `$this`: `$this::` and `static::` the runtime class,
     * `self::` and `parent::` as the declaring class names them, and an ancestor's name for a method it does not
     * declare static.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return ReflectionClass<object>|null
     */
    private function staticCallClass(StaticCall $call, string $method, ReflectionClass $declaring): ?ReflectionClass
    {
        $class = $call->class;

        if (! $class instanceof Name) {
            return self::isThis($class) ? $this->runtime : null;
        }

        if ($class->isSpecialClassName()) {
            return AnalysisScope::relativeClassOf($class, $declaring, $this->runtime);
        }

        $name = $class->toString();

        return class_exists($name) && is_a($this->runtime->getName(), $name, true)
            && (! method_exists($name, $method) || ! new ReflectionMethod($name, $method)->isStatic())
            ? new ReflectionClass($name)
            : null;
    }
}
