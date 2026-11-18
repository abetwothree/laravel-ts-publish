<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Pipe;
use PhpParser\Node\Expr\BitwiseNot;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Object_ as ObjectCast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\ErrorSuppress;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionType;

/**
 * Whether a value a call may run changes a discriminant's read when it runs: a callback a resource helper calls, or a
 * value handed to another callee, which may call it. One judgement serves both. The scan that owns the read answers
 * for the methods it runs on `$this` and for the objects that hold the read.
 *
 * @internal
 */
final class ClosureReach
{
    /** The return types, lowercased, that may hold a closure, alone or in a union or intersection. */
    private const array CLOSURE_HOLDERS = ['mixed', 'callable', 'closure', 'object', 'iterable'];

    /** @var array<string, true> the `$this` methods whose `return` expressions are read, or being read */
    private array $returns = [];

    /**
     * A judge for one scan.
     *
     * @param  bool  $proxied  whether `$this` forwards a method it lacks to the model
     * @param  Closure(ReflectionClass<object>, string, int): bool  $methodMayWrite  whether running a method on
     *                                                                               `$this` may change the read
     * @param  Closure(Expr, int): bool  $holdsRead  whether an object read is the subject or holds the read
     */
    public function __construct(
        private readonly CallableTargets $targets,
        private readonly MethodLocator $locator,
        private readonly bool $proxied,
        private readonly Closure $methodMayWrite,
        private readonly Closure $holdsRead,
    ) {}

    /**
     * Whether a value is a closure or an arrow function written there, whose body the scan reads in place.
     */
    public static function isClosure(?Expr $value): bool
    {
        return $value instanceof ClosureExpr || $value instanceof ArrowFunction;
    }

    /**
     * Whether a value a call may run, as a helper's callback or handed to another callee, may change the read: a
     * callable by the method it runs, a value that can hold no closure never, one that yields others by each of
     * them, a closure by its body, which the scan reads in place, a call by its result, and anything else may.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the value is written
     */
    public function mayWrite(Expr $value, ReflectionClass $declaring, int $depth): bool
    {
        if (($value instanceof CallLike && $value->isFirstClassCallable())
            || $this->targets->callableTarget($value, $declaring) !== null
        ) {
            return $this->runMayWrite($value, $declaring, $depth);
        }

        $yields = self::yields($value);

        return match (true) {
            self::holdsNoClosure($value), self::isClosure($value) => false,
            $yields !== null => array_any(
                $yields,
                fn (Expr $yielded): bool => $this->mayWrite($yielded, $declaring, $depth),
            ),
            $value instanceof CallLike => $this->resultMayWrite($value, $declaring, $depth),
            default => true,
        };
    }

    /**
     * Whether a value handed to a callee may let it change the read: the subject or an object the read is a member
     * of, a callable by the method it runs, such a value in an array, a branch, an assignment or a closure's return,
     * and any other value as mayWrite() judges it, but a local, taken as data as the subject's aliases are.
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
        $yields = self::yields($value);

        return match (true) {
            $value instanceof Variable, $value instanceof PropertyFetch,
            $value instanceof NullsafePropertyFetch => ($this->holdsRead)($value, $depth),
            $value instanceof Array_ => array_any($value->items, fn (ArrayItem $item): bool => $holds($item->value)),
            $yields !== null => array_any($yields, $holds),
            $value instanceof ArrowFunction => $holds($value->expr),
            $value instanceof ClosureExpr => array_any(
                new NodeFinder()->findInstanceOf($value->stmts, Return_::class),
                fn (Return_ $return): bool => $holds($return->expr),
            ),
            default => $this->mayWrite($value, $declaring, $depth),
        };
    }

    /**
     * Whether calling a callable value may change the read: one naming a method run on `$this`, or another class's
     * method, through that method (one it cannot name may), one bound to an object holding the read as the model's
     * method; any other binds nothing of the subject.
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
     * Whether a value can hold no closure: a scalar, a constant, an array literal, a `new`, an operator's or a cast's
     * result but `??`, `|>` and `(object)`, `isset()`, `empty()`, `instanceof`, `$this`, or a member read.
     */
    private static function holdsNoClosure(Expr $value): bool
    {
        return $value instanceof Scalar || $value instanceof ConstFetch || $value instanceof ClassConstFetch
            || $value instanceof Array_ || $value instanceof New_ || CallableTargets::isThis($value)
            || ($value instanceof BinaryOp && ! $value instanceof Coalesce && ! $value instanceof Pipe)
            || ($value instanceof Cast && ! $value instanceof ObjectCast)
            || $value instanceof BooleanNot || $value instanceof BitwiseNot || $value instanceof UnaryMinus
            || $value instanceof UnaryPlus || $value instanceof PreInc || $value instanceof PreDec
            || $value instanceof PostInc || $value instanceof PostDec || $value instanceof Isset_
            || $value instanceof Empty_ || $value instanceof Instanceof_
            // A member read is taken to hold data: following a closure one holds needs every write to it, a known gap.
            || self::readsMember($value);
    }

    /**
     * Whether a value reads a property or a key of a variable, through any chain of them.
     */
    private static function readsMember(Expr $value): bool
    {
        $root = $value;

        while ($root instanceof PropertyFetch || $root instanceof NullsafePropertyFetch
            || $root instanceof ArrayDimFetch
        ) {
            $root = $root->var;
        }

        return $root !== $value && $root instanceof Variable && is_string($root->name);
    }

    /**
     * The values an expression may yield as it is: a coalesce's sides, an `(object)` cast's operand, an assignment's
     * value (and a `??=` target's), a branch's, a match arm's, `@`'s and `clone`'s operand, and the callable
     * `Closure::fromCallable()` wraps; null for any other expression.
     *
     * @return list<Expr>|null
     */
    private static function yields(Expr $value): ?array
    {
        return match (true) {
            $value instanceof Coalesce => [$value->left, $value->right],
            $value instanceof ObjectCast, $value instanceof ErrorSuppress, $value instanceof Clone_ => [$value->expr],
            $value instanceof Assign, $value instanceof AssignRef => [$value->expr],
            $value instanceof CoalesceAssign => [$value->var, $value->expr],
            $value instanceof AssignOp => [],
            $value instanceof Ternary => [$value->if ?? $value->cond, $value->else],
            $value instanceof Match_ => array_values(array_map(
                static fn (MatchArm $arm): Expr => $arm->body,
                $value->arms,
            )),
            $value instanceof StaticCall && self::wrapsCallable($value) => array_values(array_map(
                static fn (Arg $arg): Expr => $arg->value,
                $value->getArgs(),
            )),
            default => null,
        };
    }

    /**
     * Whether a static call is `Closure::fromCallable()`, which returns the callable it is handed as a closure.
     */
    private static function wrapsCallable(StaticCall $call): bool
    {
        return $call->class instanceof Name && $call->class->toLowerString() === 'closure'
            && $call->name instanceof Identifier && $call->name->toLowerString() === 'fromcallable'
            && ! $call->isFirstClassCallable();
    }

    /**
     * Whether a type may hold a closure: one CLOSURE_HOLDERS lists, alone or in a union or intersection.
     */
    private static function mayHoldClosure(ReflectionType $type): bool
    {
        $names = preg_split('/[|&()?\s]+/', strtolower((string) $type), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_intersect(self::CLOSURE_HOLDERS, $names) !== [];
    }

    /**
     * The values a body's `return` statements return, outside the closures, functions and classes it holds.
     *
     * @param  array<Node>  $stmts
     * @return list<Expr>
     */
    private static function returnedValues(array $stmts): array
    {
        $values = [];
        $stack = $stmts;

        while ($stack !== []) {
            $node = array_pop($stack);

            if (! $node instanceof Node || $node instanceof FunctionLike || $node instanceof ClassLike) {
                continue;
            }

            if ($node instanceof Return_ && $node->expr !== null) {
                $values[] = $node->expr;
            }

            foreach ($node->getSubNodeNames() as $name) {
                array_push($stack, ...(is_array($node->{$name}) ? $node->{$name} : [$node->{$name}]));
            }
        }

        return $values;
    }

    /**
     * Whether a call's result may carry a closure that changes the read. Only a method run on `$this` binds the
     * subject without being handed it, and the scan judges a call handed it, or made on it, where the call is made:
     * such a method by its native return type, else by what its body returns; a call no scan follows may.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function resultMayWrite(CallLike $call, ReflectionClass $declaring, int $depth): bool
    {
        $onThis = $this->targets->thisMethod($call, $declaring);

        if ($onThis === null) {
            return ($call instanceof FuncCall && ! $call->name instanceof Name)
                || (($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)
                    && ! $call->name instanceof Identifier)
                || ($call instanceof StaticCall && ! $call->class instanceof Name);
        }

        [$class, $method] = $onThis;

        if (! $class->hasMethod($method)) {
            return ! $this->proxied || ! ReadOnlyCalls::readsModel($method);
        }

        $type = $class->getMethod($method)->getReturnType();

        return ($type === null || self::mayHoldClosure($type)) && $this->returnsMayWrite($class, $method, $depth);
    }

    /**
     * Whether what a method run on `$this` returns may change the read, read as deep as the scan reads a body; a body
     * any deeper, or one the locator cannot read, may. One read already, or being read, counts where it is read, since
     * a value that may change the read ends the whole scan.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function returnsMayWrite(ReflectionClass $class, string $method, int $depth): bool
    {
        $key = $class->getName().'::'.strtolower($method);

        if (isset($this->returns[$key])) {
            return false;
        }

        $body = $depth < InterveningWrites::DEPTH
            ? $this->locator->locate($class->getName(), $method)?->method->stmts
            : null;

        if ($body === null) {
            return true;
        }

        $this->returns[$key] = true;
        $declaring = $class->getMethod($method)->getDeclaringClass();

        return array_any(
            self::returnedValues($body),
            fn (Expr $returned): bool => $this->mayWrite($returned, $declaring, $depth + 1),
        );
    }
}
