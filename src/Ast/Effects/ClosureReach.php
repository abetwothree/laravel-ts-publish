<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

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
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BinaryOp\Pipe;
use PhpParser\Node\Expr\BitwiseNot;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Object_ as ObjectCast;
use PhpParser\Node\Expr\ClassConstFetch;
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
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionType;

/**
 * Whether a value a call may run changes a discriminant's read when it runs, by what kind of value it is and how it is
 * used (ValueUse): a resource helper's callback, a value handed to another callee, or one called outright. The scan
 * that owns the read answers for the methods it runs on `$this` and for the objects that hold the read.
 *
 * @internal
 */
final readonly class ClosureReach
{
    /** The return types, lowercased, that may hold a closure, alone or in a union or intersection. */
    private const array CLOSURE_HOLDERS = ['mixed', 'callable', 'closure', 'object', 'iterable'];

    /** The return types, lowercased, that can hold no callable, besides a class with no `__invoke()`. */
    private const array CALLABLE_FREE = ['int', 'float', 'bool', 'null', 'void', 'never', 'false', 'true'];

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
        private BodyReads $reads,
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
     * Whether running a value may change the read, by its kind and its use: a value that yields others by each, a
     * closure by its body, which the scan reads in place, a callable by the method it runs, a call by its result, a
     * local where it is run, a string or an array where any callable runs, a value holding no closure never.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the value is written
     */
    public function mayRun(Expr $value, ValueUse $use, ReflectionClass $declaring, int $depth): bool
    {
        $yields = self::yields($value);

        if ($yields !== null) {
            return array_any($yields, fn (Expr $yielded): bool => $this->mayRun($yielded, $use, $declaring, $depth));
        }

        if ($value instanceof CallLike) {
            return $this->callMayRun($value, $use, $declaring, $depth);
        }

        $spelled = $use !== ValueUse::Callback && self::spellsCallable($value);

        return match (true) {
            self::isClosure($value) => false,
            $value instanceof Pipe => $this->pipeResultMayRun($value, $use, $declaring, $depth),
            $value instanceof Variable && ! CallableTargets::isThis($value) => $use !== ValueUse::Handed,
            $spelled => $this->spelledMayRun($value, $declaring, $depth),
            self::holdsNoClosure($value) => false,
            default => true,
        };
    }

    /**
     * Whether a value handed to a callee may let it change the read: the subject or an object the read is a member
     * of, such a value in an array but a callable pair, a branch, an assignment or a closure's return, or a value
     * mayRun() refuses as handed.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    public function hands(?Expr $value, ReflectionClass $declaring, int $depth): bool
    {
        if ($value === null) {
            return false;
        }

        $holds = fn (?Expr $part): bool => $this->hands($part, $declaring, $depth);
        $yields = self::yields($value);

        return match (true) {
            $value instanceof Variable, $value instanceof PropertyFetch,
            $value instanceof NullsafePropertyFetch => ($this->holdsRead)($value, $depth),
            $value instanceof Array_ && ! self::pairsCallable($value) => array_any(
                $value->items,
                fn (ArrayItem $item): bool => $holds($item->value),
            ),
            $yields !== null => array_any($yields, $holds),
            $value instanceof ArrowFunction => $holds($value->expr),
            $value instanceof ClosureExpr => array_any(
                new NodeFinder()->findInstanceOf($value->stmts, Return_::class),
                fn (Return_ $return): bool => $holds($return->expr),
            ),
            default => $this->mayRun($value, ValueUse::Handed, $declaring, $depth),
        };
    }

    /**
     * Whether a value can hold no closure, nor name a callable that runs on `$this` where a string or an array can:
     * a scalar, a constant, an array, a `new`, an operator's or a cast's result but `??`, `|>` and `(object)`,
     * `isset()`, `empty()`, `instanceof`, `$this`, or a member read with any root.
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
            || $value instanceof PropertyFetch || $value instanceof NullsafePropertyFetch
            || $value instanceof StaticPropertyFetch || $value instanceof ArrayDimFetch;
    }

    /**
     * The values an expression may yield as it is: a coalesce's sides, an `(object)` cast's and `@`'s operand, an
     * assignment's value (and a `??=` target's), a branch's and a match arm's; null for any other expression.
     *
     * @return list<Expr>|null
     */
    private static function yields(Expr $value): ?array
    {
        return match (true) {
            $value instanceof Coalesce => [$value->left, $value->right],
            $value instanceof ObjectCast, $value instanceof ErrorSuppress => [$value->expr],
            $value instanceof Assign, $value instanceof AssignRef => [$value->expr],
            $value instanceof CoalesceAssign => [$value->var, $value->expr],
            $value instanceof AssignOp => [],
            $value instanceof Ternary => [$value->if ?? $value->cond, $value->else],
            $value instanceof Match_ => array_values(array_map(
                static fn (MatchArm $arm): Expr => $arm->body,
                $value->arms,
            )),
            default => null,
        };
    }

    /**
     * Whether a value is a string, an array or a joined string, which may spell a callable.
     */
    private static function spellsCallable(Expr $value): bool
    {
        return $value instanceof String_ || $value instanceof Array_ || $value instanceof Concat
            || $value instanceof InterpolatedString;
    }

    /**
     * Whether an array is a callable pair: two items, the second a method's name, a class's, or a name the source
     * computes.
     */
    private static function pairsCallable(Array_ $value): bool
    {
        $name = count($value->items) === 2 ? $value->items[1]->value : null;

        return $name instanceof String_ || $name instanceof Variable
            || ($name instanceof ClassConstFetch && ReadOnlyCalls::isClassName($name));
    }

    /**
     * Whether a call is `Closure::fromCallable()`, which returns the callable it is handed as a closure.
     */
    private static function wrapsCallable(CallLike $call): bool
    {
        return $call instanceof StaticCall && $call->class instanceof Name
            && $call->class->toLowerString() === 'closure'
            && $call->name instanceof Identifier && $call->name->toLowerString() === 'fromcallable'
            && ! $call->isFirstClassCallable();
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
     * Whether running a call's value may change the read: a first-class callable by what it runs,
     * `Closure::fromCallable()` by the callable it wraps, and any other call by its result.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callMayRun(CallLike $call, ValueUse $use, ReflectionClass $declaring, int $depth): bool
    {
        return match (true) {
            $call->isFirstClassCallable() => $this->firstClassMayRun($call, $declaring, $depth),
            self::wrapsCallable($call) => $this->wrappedMayRun($call, $use, $declaring, $depth),
            default => $this->resultMayRun($call, $use, $declaring, $depth),
        };
    }

    /**
     * Whether running a first-class callable may change the read: one of a method run on `$this` by that method, one
     * bound to an object holding the read as the model's method, one of a computed callee as that callee is called,
     * and a function's or another object's never.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function firstClassMayRun(CallLike $callable, ReflectionClass $declaring, int $depth): bool
    {
        if ($callable instanceof FuncCall) {
            return ! $callable->name instanceof Name
                && $this->mayRun($callable->name, ValueUse::Called, $declaring, $depth);
        }

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
     * Whether running what `Closure::fromCallable()` wraps may change the read: the callable it is handed, as handed
     * where the closure is, and as called where a helper calls the closure.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function wrappedMayRun(CallLike $call, ValueUse $use, ReflectionClass $declaring, int $depth): bool
    {
        $wrapped = $use === ValueUse::Callback ? ValueUse::Called : $use;

        return array_any(
            $call->getArgs(),
            fn (Arg $arg): bool => $this->mayRun($arg->value, $wrapped, $declaring, $depth),
        );
    }

    /**
     * Whether a pipe's result may run code that changes the read: what the method its right side runs on `$this`
     * returns; any other callable's result is safe.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function pipeResultMayRun(Pipe $pipe, ValueUse $use, ReflectionClass $declaring, int $depth): bool
    {
        $target = $this->targets->callableTarget($pipe->right, $declaring);

        return is_array($target) && $this->returnMayRun($target[0], $target[1], $use, $depth);
    }

    /**
     * Whether a call's result may run code that changes the read: only a method run on `$this` binds the subject
     * without being handed it, and the scan judges a call handed it, or made on it, where the call is made.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function resultMayRun(CallLike $call, ValueUse $use, ReflectionClass $declaring, int $depth): bool
    {
        $onThis = $this->targets->thisMethod($call, $declaring);

        return $onThis !== null && $this->returnMayRun($onThis[0], $onThis[1], $use, $depth);
    }

    /**
     * Whether what a method run on `$this` returns may run code that changes the read: none where its native return
     * type cannot hold what the use runs, else by its `return` expressions, read as BodyReads reads a body.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function returnMayRun(ReflectionClass $class, string $method, ValueUse $use, int $depth): bool
    {
        if (! $class->hasMethod($method)) {
            return false;
        }

        $type = $class->getMethod($method)->getReturnType();

        return ($type === null || $this->typeMayHold($type, $use, $class)) && $this->reads->mayWrite(
            'returns:'.$use->name,
            $class,
            $method,
            $depth,
            fn (array $body, ReflectionClass $declaring, int $inner): bool => array_any(
                self::returnedValues($body),
                fn (Expr $returned): bool => $this->mayRun($returned, $use, $declaring, $inner),
            ),
        );
    }

    /**
     * Whether a string, an array or a joined string a callee runs may change the read: one naming a method run on
     * `$this` by its body, one naming one by a name the source does not spell, and a pair led by an object holding
     * the read as the model's method.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function spelledMayRun(Expr $value, ReflectionClass $declaring, int $depth): bool
    {
        $target = $this->targets->callableTarget($value, $declaring);
        $holder = $value instanceof Array_ && count($value->items) === 2 ? $value->items[0]->value : null;
        $name = $holder === null ? null : $value->items[1]->value;

        return match (true) {
            is_array($target) => ($this->methodMayWrite)($target[0], $target[1], $depth),
            $target === false => true,
            default => $holder !== null && ! CallableTargets::isThis($holder) && ($this->holdsRead)($holder, $depth)
                && (! $name instanceof String_ || ! ReadOnlyCalls::readsModel($name->value)),
        };
    }

    /**
     * Whether a native return type may hold what a use runs: a closure for a helper's callback, any callable else,
     * which only a scalar type that is no string, `void`, `never` or a class with no `__invoke()` cannot.
     *
     * @param  ReflectionClass<object>  $class  the class whose method declares the type
     */
    private function typeMayHold(ReflectionType $type, ValueUse $use, ReflectionClass $class): bool
    {
        $names = preg_split('/[|&()?\s]+/', strtolower((string) $type), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if ($use === ValueUse::Callback) {
            return array_intersect(self::CLOSURE_HOLDERS, $names) !== [];
        }

        return array_any($names, fn (string $name): bool => ! in_array($name, self::CALLABLE_FREE, true)
            && ! $this->namesUninvokable($name, $class));
    }

    /**
     * Whether a type's name names a class with no `__invoke()`: `self`, `static` and `parent` as the class declaring
     * the type names them, or a class here declared.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function namesUninvokable(string $name, ReflectionClass $class): bool
    {
        $named = match ($name) {
            'self' => $class->getName(),
            'static' => $this->runtime->getName(),
            'parent' => $class->getParentClass() === false ? '' : $class->getParentClass()->getName(),
            default => $name,
        };

        return class_exists($named) && ! method_exists($named, '__invoke');
    }
}
