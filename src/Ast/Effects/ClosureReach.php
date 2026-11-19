<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallablePair;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\NativeTypeNames;
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
use PhpParser\Node\Stmt;
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
 * @phpstan-type HeldMethodJudge = Closure(Expr, string|null, ReflectionClass<object>, bool): bool
 *
 * @internal
 */
final readonly class ClosureReach
{
    /** The return types, lowercased, that may hold a closure, alone or in a union or intersection. */
    private const array CLOSURE_HOLDERS = ['mixed', 'callable', 'closure', 'object', 'iterable'];

    /** The return types, lowercased, that can hold no callable, besides a class with no `__invoke()`. */
    private const array CALLABLE_FREE = ['int', 'float', 'bool', 'null', 'void', 'never', 'false', 'true'];

    /** The local an open array's unspelled method is read through, named as no source can spell a variable. */
    private const string UNSPELLED_METHOD = 'unspelled method';

    /**
     * A judge for one scan.
     *
     * @param  ReflectionClass<object>  $runtime  the class `$this` and `static` name
     * @param  Closure(ReflectionClass<object>, string): bool  $methodMayWrite  whether running a method on `$this`
     *                                                                          may change the read
     * @param  Closure(Expr, bool): bool  $holdsRead  whether an object read is the subject or holds the read, in a
     *                                                method's body or in the window's items
     * @param  HeldMethodJudge  $heldMethodMayWrite  whether running a method, null where its name is computed, on an
     *                                               object read may change the read
     */
    public function __construct(
        private CallableTargets $targets,
        private BodyReads $reads,
        private ReflectionClass $runtime,
        private Closure $methodMayWrite,
        private Closure $holdsRead,
        private Closure $heldMethodMayWrite,
    ) {}

    /**
     * Whether a value is a closure or an arrow function written there, whose body the scan reads in place.
     *
     * @phpstan-assert-if-true ClosureExpr|ArrowFunction $value
     */
    public static function isClosure(?Expr $value): bool
    {
        return $value instanceof ClosureExpr || $value instanceof ArrowFunction;
    }

    /**
     * The values a body's `return` statements return, outside the closures, functions and classes it holds.
     *
     * @param  array<Node>  $stmts
     * @return list<Expr>
     */
    public static function returnedValues(array $stmts): array
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
     * Whether running a value may change the read, by its kind and its use: a value that yields others by each, a
     * closure by its body, which the scan reads in place, a callable by the method it runs, a call by its result, a
     * local where it is run, a string or an array where any callable runs, a value holding no closure never.
     *
     * @param  ReflectionClass<object>  $declaring  the class `self` names where the value is written
     */
    public function mayRun(Expr $value, ValueUse $use, ReflectionClass $declaring, bool $inBody): bool
    {
        $yields = self::yields($value);

        if ($yields !== null) {
            return array_any($yields, fn (Expr $yielded): bool => $this->mayRun($yielded, $use, $declaring, $inBody));
        }

        if ($value instanceof CallLike) {
            return $this->callMayRun($value, $use, $declaring, $inBody);
        }

        $spelled = $use !== ValueUse::Callback && self::spellsCallable($value);

        return match (true) {
            self::isClosure($value) => false,
            $value instanceof Pipe => $this->pipeResultMayRun($value, $use, $declaring, $inBody),
            $value instanceof Variable && ! CallableTargets::isThis($value) => $use !== ValueUse::Handed,
            $spelled => $this->spelledMayRun($value, $declaring, $inBody),
            self::holdsNoClosure($value) => false,
            default => true,
        };
    }

    /**
     * Whether a value handed to a callee may let it change the read: the subject or an object the read is a member
     * of, such a value in an array, a branch, an assignment, a closure's return or a pipe's, or a value mayRun()
     * refuses as handed.
     *
     * @param  ReflectionClass<object>  $declaring
     * @param  bool  $runByBuiltIn  whether the value lands in a `callable` or `Closure` parameter of a callee PHP
     *                              itself implements, which may run it but sets no property of it
     */
    public function hands(?Expr $value, ReflectionClass $declaring, bool $inBody, bool $runByBuiltIn): bool
    {
        if ($value === null) {
            return false;
        }

        $holds = fn (?Expr $part): bool => $this->hands($part, $declaring, $inBody, $runByBuiltIn);
        $yields = self::yields($value);

        return match (true) {
            self::isPath($value) => ($this->holdsRead)($value, $inBody),
            $value instanceof Array_ => $this->handsArray($value, $declaring, $inBody, $runByBuiltIn),
            $value instanceof Pipe && self::isClosure($value->right) => array_any(
                self::handedBack($value->right),
                $holds,
            ),
            $yields !== null => array_any($yields, $holds),
            self::isClosure($value) => array_any(self::handedBack($value), $holds),
            default => $this->mayRun($value, ValueUse::Handed, $declaring, $inBody),
        };
    }

    /**
     * Whether a value a body returns, outside the closures it holds, may run code that changes the read, used as $use;
     * BodyReads judges a `$this` method's returns by it.
     *
     * @param  array<Stmt>  $body
     * @param  ReflectionClass<object>  $declaring  the class `self` names in the body
     */
    public function returnedMayRun(array $body, ValueUse $use, ReflectionClass $declaring): bool
    {
        return array_any(
            self::returnedValues($body),
            fn (Expr $returned): bool => $this->mayRun($returned, $use, $declaring, true),
        );
    }

    /**
     * Whether a value is a variable or a property of one, which the scan matches against the read.
     */
    private static function isPath(Expr $value): bool
    {
        return $value instanceof Variable || $value instanceof PropertyFetch || $value instanceof NullsafePropertyFetch;
    }

    /**
     * Whether a value can hold no closure, nor name a callable that runs on `$this` where a string or an array can:
     * a scalar, a constant, an array, an operator's or a cast's result but `??`, `|>` and `(object)`,
     * `isset()`, `empty()`, `instanceof`, `$this`, or a member read with any root.
     */
    private static function holdsNoClosure(Expr $value): bool
    {
        return $value instanceof Scalar || $value instanceof ConstFetch || $value instanceof ClassConstFetch
            || $value instanceof Array_ || CallableTargets::isThis($value)
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
     * Whether an array may spell a callable pair: one that may be any pair, or one whose method is a name, a constant,
     * or a name the source computes in a local.
     */
    private static function pairsCallable(CallablePair|false|null $pair): bool
    {
        $name = $pair instanceof CallablePair ? $pair->method : null;

        return $pair === false
            || $name instanceof String_ || $name instanceof Variable || $name instanceof ClassConstFetch;
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
     * The values a closure or an arrow function written there returns when it is called.
     *
     * @return list<Expr>
     */
    private static function closureReturns(ClosureExpr|ArrowFunction $closure): array
    {
        return $closure instanceof ArrowFunction ? [$closure->expr] : self::returnedValues($closure->stmts);
    }

    /**
     * The values a closure or an arrow function written there may hand back: what it returns, or, where a value it
     * returns reads a variable or a static property or makes a call, which may carry a closure the body builds, what
     * every `return` in its body returns, its nested closures' included.
     *
     * @return list<Expr>
     */
    private static function handedBack(ClosureExpr|ArrowFunction $closure): array
    {
        $returns = self::closureReturns($closure);
        $carries = static fn (Node $node): bool => $node instanceof Variable || $node instanceof StaticPropertyFetch
            || $node instanceof CallLike;

        if ($closure instanceof ArrowFunction || new NodeFinder()->findFirst($returns, $carries) === null) {
            return $returns;
        }

        return array_values(array_filter(array_map(
            static fn (Return_ $return): ?Expr => $return->expr,
            new NodeFinder()->findInstanceOf($closure->stmts, Return_::class),
        )));
    }

    /**
     * Whether an array handed to a callee may let it change the read: through any item that hands the subject, but,
     * in a pair naming a method that lands where a built-in takes a callable, the object the pair names, which the
     * built-in may only run; or through the callable the array may spell.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function handsArray(Array_ $value, ReflectionClass $declaring, bool $inBody, bool $runByBuiltIn): bool
    {
        $pair = CallablePair::of($value);
        $runOnly = $runByBuiltIn && $pair instanceof CallablePair && $pair->method instanceof String_;

        return array_any(
            $value->items,
            fn (ArrayItem $item): bool => ! ($runOnly && self::isPath($item->value))
                && $this->hands($item->value, $declaring, $inBody, $runByBuiltIn),
        ) || (self::pairsCallable($pair) && $this->mayRun($value, ValueUse::Handed, $declaring, $inBody));
    }

    /**
     * Whether running a call's value may change the read: a first-class callable by what it runs,
     * `Closure::fromCallable()` by the callable it wraps, and any other call by its result.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callMayRun(CallLike $call, ValueUse $use, ReflectionClass $declaring, bool $inBody): bool
    {
        return match (true) {
            $call->isFirstClassCallable() => $this->firstClassMayRun($call, $declaring, $inBody),
            self::wrapsCallable($call) => $this->wrappedMayRun($call, $use, $declaring, $inBody),
            default => $this->resultMayRun($call, $use, $declaring),
        };
    }

    /**
     * Whether running a first-class callable may change the read: one of a method run on `$this` by that method, one
     * bound to an object holding the read as the model's method, one of a computed callee as that callee is called,
     * and a function's or another object's never.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function firstClassMayRun(CallLike $callable, ReflectionClass $declaring, bool $inBody): bool
    {
        if ($callable instanceof FuncCall) {
            return ! $callable->name instanceof Name
                && $this->mayRun($callable->name, ValueUse::Called, $declaring, $inBody);
        }

        $target = $this->targets->callableTarget($callable, $declaring);

        return match (true) {
            is_array($target) => ($this->methodMayWrite)($target[0], $target[1]),
            $target === false => true,
            default => ($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && ($this->heldMethodMayWrite)(
                    $callable->var,
                    $callable->name instanceof Identifier ? $callable->name->toString() : null,
                    $declaring,
                    $inBody,
                ),
        };
    }

    /**
     * Whether running what `Closure::fromCallable()` wraps may change the read: the callable it is handed, as handed
     * where the closure is, and as called where a helper calls the closure.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function wrappedMayRun(CallLike $call, ValueUse $use, ReflectionClass $declaring, bool $inBody): bool
    {
        $wrapped = $use === ValueUse::Callback ? ValueUse::Called : $use;

        return array_any(
            $call->getArgs(),
            fn (Arg $arg): bool => $this->mayRun($arg->value, $wrapped, $declaring, $inBody),
        );
    }

    /**
     * Whether a pipe's result may run code that changes the read: what a closure on its right side, or the method
     * it runs on `$this`, returns; any other callable's result is safe.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function pipeResultMayRun(Pipe $pipe, ValueUse $use, ReflectionClass $declaring, bool $inBody): bool
    {
        if (self::isClosure($pipe->right)) {
            return array_any(
                self::closureReturns($pipe->right),
                fn (Expr $returned): bool => $this->mayRun($returned, $use, $declaring, $inBody),
            );
        }

        $target = $this->targets->callableTarget($pipe->right, $declaring);

        return is_array($target) && $this->returnMayRun($target[0], $target[1], $use);
    }

    /**
     * Whether a call's result may run code that changes the read: only a method run on `$this` binds the subject
     * without being handed it, and the scan judges a call handed it, or made on it, where the call is made.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function resultMayRun(CallLike $call, ValueUse $use, ReflectionClass $declaring): bool
    {
        $onThis = $this->targets->thisMethod($call, $declaring);

        return $onThis !== null && $this->returnMayRun($onThis[0], $onThis[1], $use);
    }

    /**
     * Whether what a method run on `$this` returns may run code that changes the read: none where its native return
     * type cannot hold what the use runs, else by its `return` expressions, read as BodyReads reads a body.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function returnMayRun(ReflectionClass $class, string $method, ValueUse $use): bool
    {
        if (! $class->hasMethod($method)) {
            return false;
        }

        $type = $class->getMethod($method)->getReturnType();

        return ($type === null || $this->typeMayHold($type, $use, $class))
            && $this->reads->returnsMayRun($class, $method, $use);
    }

    /**
     * Whether a string, an array or a joined string a callee runs may change the read: one naming a method run on
     * `$this` by its body, one naming one by a name the source does not spell, a pair whose object holds the read, as
     * the model's method, and an open array as a pair of any of its own items and a method the source does not spell.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function spelledMayRun(Expr $value, ReflectionClass $declaring, bool $inBody): bool
    {
        $pair = $value instanceof Array_ ? CallablePair::of($value) : null;

        if ($value instanceof Array_ && $pair === false) {
            return array_any($value->items, fn (ArrayItem $item): bool => $this->spelledMayRun(
                new Array_([new ArrayItem($item->value), new ArrayItem(new Variable(self::UNSPELLED_METHOD))]),
                $declaring,
                $inBody,
            ));
        }

        $target = $this->targets->callableTarget($value, $declaring);

        return match (true) {
            is_array($target) => ($this->methodMayWrite)($target[0], $target[1]),
            $target === false => true,
            default => $pair instanceof CallablePair && ! CallableTargets::isThis($pair->holder)
                && ($this->heldMethodMayWrite)(
                    $pair->holder,
                    $pair->method instanceof String_ ? $pair->method->value : null,
                    $declaring,
                    $inBody,
                ),
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
        $names = NativeTypeNames::of($type);

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
