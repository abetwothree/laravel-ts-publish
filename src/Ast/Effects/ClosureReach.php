<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use Closure;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * Whether a value a call may run changes a discriminant's read when it runs: a callback a resource helper calls, or a
 * value handed to another callee. The scan that owns the read answers for the methods it runs on `$this` and for the
 * objects that hold the read.
 *
 * @internal
 */
final readonly class ClosureReach
{
    /** The return types, lowercased, that may hold a closure, alone or in a union or intersection. */
    private const array CLOSURE_HOLDERS = ['mixed', 'callable', 'closure', 'object', 'iterable'];

    /**
     * A judge for one scan.
     *
     * @param  ReflectionClass<object>  $runtime  the class `$this` and `static` name
     * @param  Closure(ReflectionClass<object>, string, int): bool  $methodMayWrite  whether running a method on
     *                                                                               `$this` may change the read
     * @param  Closure(Expr, int): bool  $holdsRead  whether an object read is the subject or holds the read
     */
    public function __construct(
        private CallableTargets $targets,
        private ByReferenceArguments $references,
        private ReflectionClass $runtime,
        private Closure $methodMayWrite,
        private Closure $holdsRead,
    ) {}

    /**
     * Whether a value is a closure or an arrow function written there, whose body the scan reads in place.
     */
    public static function isClosure(?Expr $value): bool
    {
        return $value instanceof ClosureExpr || $value instanceof ArrowFunction;
    }

    /**
     * Whether one callback a helper calls may change the read. It is safe only where it holds no closure, is a closure
     * the scan reads in place, a first-class callable of a method whose body may not change the read, or a call whose
     * callee's return type holds no closure, or yields only such values; anything else may.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    public function callbackMayWrite(Expr $callback, ReflectionClass $declaring, int $depth): bool
    {
        $yields = fn (Expr $value): bool => $this->callbackMayWrite($value, $declaring, $depth);

        return match (true) {
            self::holdsNoClosure($callback), self::isClosure($callback) => false,
            $callback instanceof CallLike => $callback->isFirstClassCallable()
                ? $this->methodCallableMayWrite($callback, $declaring, $depth)
                : $this->mayReturnClosure($callback, $declaring),
            $callback instanceof Assign, $callback instanceof AssignRef => $yields($callback->expr),
            $callback instanceof Ternary => $yields($callback->if ?? $callback->cond) || $yields($callback->else),
            $callback instanceof Coalesce => $yields($callback->left) || $yields($callback->right),
            $callback instanceof Match_ => array_any($callback->arms, fn (MatchArm $arm): bool => $yields($arm->body)),
            default => true,
        };
    }

    /**
     * Whether a value may be the subject or an object the read is a member of, or a callable that may change the read
     * when the callee calls it: such a path or callable, or an array, a branch, an assignment or a closure's return
     * that may hold one.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    public function hands(?Expr $value, ReflectionClass $declaring, int $depth): bool
    {
        if ($value === null) {
            return false;
        }

        if (($value instanceof CallLike && $value->isFirstClassCallable())
            || $this->targets->callableTarget($value, $declaring) !== null
        ) {
            return $this->runMayWrite($value, $declaring, $depth);
        }

        $holds = fn (?Expr $part): bool => $this->hands($part, $declaring, $depth);

        return match (true) {
            $value instanceof Variable, $value instanceof PropertyFetch,
            $value instanceof NullsafePropertyFetch => ($this->holdsRead)($value, $depth),
            $value instanceof Array_ => array_any($value->items, fn (ArrayItem $item): bool => $holds($item->value)),
            $value instanceof Ternary => $holds($value->if ?? $value->cond) || $holds($value->else),
            $value instanceof Coalesce => $holds($value->left) || $holds($value->right),
            $value instanceof Assign, $value instanceof AssignRef => $holds($value->expr),
            $value instanceof Match_ => array_any($value->arms, fn (MatchArm $arm): bool => $holds($arm->body)),
            $value instanceof ArrowFunction => $holds($value->expr),
            $value instanceof ClosureExpr => array_any(
                new NodeFinder()->findInstanceOf($value->stmts, Return_::class),
                fn (Return_ $return): bool => $holds($return->expr),
            ),
            default => false,
        };
    }

    /**
     * Whether calling a callable value may change the read: one naming a method run on `$this`, or another class's
     * static method, through that method (one it cannot name may), one bound to an object holding the read as the
     * model's method; any other binds nothing of the subject.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    public function runMayWrite(Expr $callable, ReflectionClass $declaring, int $depth): bool
    {
        $target = $this->targets->callableTarget($callable, $declaring);

        return match (true) {
            is_array($target) => ($this->methodMayWrite)($target[0], $target[1], $depth),
            $target === false => true,
            default => ($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && ($this->holdsRead)($callable->var, $depth)
                && (! $callable->name instanceof Identifier
                    || ! ReadOnlyCalls::readsModel($callable->name->toString())),
        };
    }

    /**
     * Whether a value can hold no closure: a scalar, a constant, an array literal, a `new`, `$this` or a property read.
     */
    private static function holdsNoClosure(Expr $value): bool
    {
        return $value instanceof Scalar || $value instanceof ConstFetch || $value instanceof ClassConstFetch
            || $value instanceof Array_ || $value instanceof New_ || CallableTargets::isThis($value)
            // A property is taken to hold data: following a closure one holds needs every write to it, a known gap.
            || (($value instanceof PropertyFetch || $value instanceof NullsafePropertyFetch)
                && ReadPath::of($value) !== null);
    }

    /**
     * Whether calling a first-class callable may change the read where only a method it names is read: through that
     * method's body, and any other may.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function methodCallableMayWrite(CallLike $callable, ReflectionClass $declaring, int $depth): bool
    {
        $target = $this->targets->callableTarget($callable, $declaring);

        return ! is_array($target) || ($this->methodMayWrite)($target[0], $target[1], $depth);
    }

    /**
     * Whether a call's callee may return a closure: one this cannot name, or whose return type is none or one
     * CLOSURE_HOLDERS lists.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function mayReturnClosure(CallLike $call, ReflectionClass $declaring): bool
    {
        $onThis = $this->targets->thisMethod($call, $declaring);
        $callee = $onThis === null
            ? $this->references->callee($call, $declaring, $this->runtime)
            : ($onThis[0]->hasMethod($onThis[1]) ? $onThis[0]->getMethod($onThis[1]) : null);
        $type = $callee?->getReturnType();

        return $type === null || array_intersect(
            self::CLOSURE_HOLDERS,
            preg_split('/[|&()?\s]+/', strtolower((string) $type), flags: PREG_SPLIT_NO_EMPTY) ?: [],
        ) !== [];
    }
}
