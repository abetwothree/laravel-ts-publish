<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallablePair;
use AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust\ReadOnlyCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
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
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\MagicConst\Class_;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;

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
     * The method a callable value runs on `$this`: a first-class callable, or a `Class::method` string or a pair naming
     * one that `$this`'s class or an ancestor declares non-static; false where the source spells no name, else null.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    public function callableTarget(Expr $callable, ReflectionClass $declaring): array|false|null
    {
        $relative = ReadOnlyCalls::relativeCallable($callable);

        if ($relative !== null) {
            $class = AnalysisScope::relativeClassOf(new Name($relative[0]), $declaring, $this->runtime);

            return match (true) {
                $relative[1] !== null => $class === null ? null : self::boundMethod($class, $relative[1]),
                $callable instanceof Array_ => false,
                default => null,
            };
        }

        if ($callable instanceof Concat || $callable instanceof InterpolatedString) {
            return $this->joinsOwnMethod($callable, $declaring) ? false : null;
        }

        return match (true) {
            $callable instanceof String_ => preg_match(self::NAMED_METHOD, $callable->value, $parts) === 1
                ? $this->namedMethod(new Name($parts[1]), $parts[2], $declaring)
                : null,
            $callable instanceof Array_ => $this->arrayTarget($callable, $declaring),
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
        $class = AnalysisScope::thisCallClassOf($call, $method, $declaring, $this->runtime);

        return $class === null ? null : [$class, $method];
    }

    /**
     * The parts a chain of concatenations, or an interpolated string, joins, in order.
     *
     * @return list<Node>
     */
    private static function joinedParts(Expr $expr): array
    {
        return match (true) {
            $expr instanceof Concat => [...self::joinedParts($expr->left), ...self::joinedParts($expr->right)],
            $expr instanceof InterpolatedString => array_values($expr->parts),
            default => [$expr],
        };
    }

    /**
     * The method a name runs on `$this` where the class is `$this`'s or an ancestor: one it declares non-static, or
     * one it lacks where it has `__call()`, which PHP hands the name and an API resource forwards to its model.
     *
     * @param  ReflectionClass<object>  $class
     * @return CallableTarget|null
     */
    private static function boundMethod(ReflectionClass $class, string $method): ?array
    {
        if (! $class->hasMethod($method)) {
            return $class->hasMethod('__call') ? [$class, $method] : null;
        }

        return $class->getMethod($method)->isStatic() ? null : [$class, $method];
    }

    /**
     * The class a `::class` names, with `$this::class` read as `static::class`; null for any other expression.
     */
    private static function classNamed(Node $expr): ?Name
    {
        if (! $expr instanceof ClassConstFetch || ! ReadOnlyCalls::isClassName($expr)) {
            return null;
        }

        return match (true) {
            $expr->class instanceof Name => $expr->class,
            ReadPath::isThis($expr->class) => new Name('static'),
            default => null,
        };
    }

    /**
     * The class a callable array's first item names: `X::class`, or the name of a class here declared.
     */
    private static function arrayClass(Expr $holder): ?Name
    {
        return self::classNamed($holder)
            ?? ($holder instanceof String_ && class_exists($holder->value) ? new Name($holder->value) : null);
    }

    /**
     * The method `[$this, 'm']` runs, or `[X::class, 'm']` and `['X', 'm']` where X is `$this`'s class or an
     * ancestor; false for a method of those the source does not spell, or an array that may be any pair; null for any
     * other array.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    private function arrayTarget(Array_ $callable, ReflectionClass $declaring): array|false|null
    {
        $pair = CallablePair::of($callable);

        if (! $pair instanceof CallablePair) {
            return $pair;
        }

        [$holder, $name] = [$pair->holder, $pair->method];

        if (ReadPath::isThis($holder)) {
            if (! $name instanceof String_) {
                return false;
            }

            $owner = AnalysisScope::memberClassOf($declaring, $this->runtime, $name->value, true);

            return $owner->hasMethod($name->value) && $owner->getMethod($name->value)->isStatic()
                ? null
                : [$owner, $name->value];
        }

        $class = self::arrayClass($holder);
        $owner = $class === null ? null : $this->ownClass($class, $declaring);

        return match (true) {
            $owner === null, ReadOnlyCalls::isClassName($name) => null,
            $name instanceof String_ => self::boundMethod($owner, $name->value),
            default => false,
        };
    }

    /**
     * Whether a joined string names a method of `$this`'s class by a name the source does not spell: a `::class` of
     * `$this`'s class or an ancestor, `__CLASS__` or `get_class($this)`, next to a piece that opens with `::`.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function joinsOwnMethod(Concat|InterpolatedString $joined, ReflectionClass $declaring): bool
    {
        $parts = self::joinedParts($joined);

        foreach ($parts as $index => $part) {
            $next = $parts[$index + 1] ?? null;
            $piece = match (true) {
                $next instanceof String_, $next instanceof InterpolatedStringPart => $next->value,
                default => '',
            };

            if (str_starts_with($piece, '::') && $this->namesOwnClass($part, $declaring)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a part of a joined string spells `$this`'s class or an ancestor's name.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function namesOwnClass(Node $part, ReflectionClass $declaring): bool
    {
        $class = self::classNamed($part);

        if ($class !== null) {
            return $this->ownClass($class, $declaring) !== null;
        }

        if (! $part instanceof FuncCall || ! $part->name instanceof Name || $part->isFirstClassCallable()) {
            return $part instanceof Class_;
        }

        $arguments = $part->getArgs();

        return $part->name->toLowerString() === 'get_class'
            && ($arguments === [] || ReadPath::isThis($arguments[0]->value));
    }

    /**
     * `$this`'s class or the ancestor a class name names: `self`, `static` and `parent` as the declaring class names
     * them, or a named class `$this` is; null for any other.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return ReflectionClass<object>|null
     */
    private function ownClass(Name $class, ReflectionClass $declaring): ?ReflectionClass
    {
        $named = $class->toString();

        if ($class->isSpecialClassName()) {
            return AnalysisScope::relativeClassOf($class, $declaring, $this->runtime);
        }

        return class_exists($named) && is_a($this->runtime->getName(), $named, true)
            ? new ReflectionClass($named)
            : null;
    }

    /**
     * The method a `'X::m'` string runs on `$this`, where X is `$this`'s class or an ancestor declaring it non-static.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|null
     */
    private function namedMethod(Name $class, string $method, ReflectionClass $declaring): ?array
    {
        $owner = $this->ownClass($class, $declaring);

        return $owner === null ? null : self::boundMethod($owner, $method);
    }

    /**
     * The method a first-class callable runs on `$this`; false for one whose name, or whose class, the source does not
     * spell; null for a function's, another object's or another class's.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    private function firstClassTarget(CallLike $callable, ReflectionClass $declaring): array|false|null
    {
        if (! $callable->isFirstClassCallable() || $callable instanceof FuncCall
            || (($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && ! ReadPath::isThis($callable->var))
        ) {
            return null;
        }

        $named = $callable instanceof StaticCall && $callable->class instanceof Name;

        return $this->thisMethod($callable, $declaring)
            ?? ($named && $callable->name instanceof Identifier ? null : false);
    }
}
