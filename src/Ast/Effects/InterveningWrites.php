<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\List_;
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
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;

/**
 * Whether the items of a literal's write window may change what its discriminant's read holds, so a guard after the
 * change may test another value than the discriminant sent.
 *
 * Every item counts with the writes it spells and the arguments it binds by reference, and every item but the last
 * with the calls it makes, since the last item's calls run after its own guard. A call may write where it hands its
 * callee the subject or an object holding the read, runs a `$this` method whose body may write, calls a model method
 * not known to read, or runs code no scan follows. On an API resource `$this->x` reads `$this->resource->x`.
 *
 * @phpstan-type CallableTarget = array{ReflectionClass<object>, string}
 *
 * @internal
 */
final class InterveningWrites
{
    /** How many levels of `$this` method bodies one scan reads; a body any deeper may write. */
    private const int DEPTH = 3;

    /** The built-ins that call the callable they are given first. */
    private const array CALLERS = ['call_user_func', 'call_user_func_array'];

    /** The return types, lowercased, that may hold a closure, alone or in a union or intersection. */
    private const array CLOSURE_HOLDERS = ['mixed', 'callable', 'closure', 'object', 'iterable'];

    /** @var array<string, true> the `$this` method bodies read, or being read, by class and lowercased name */
    private array $bodies = [];

    /** @var list<string> the read's steps, as steps() spells them */
    private readonly array $readSteps;

    private readonly ByReferenceArguments $references;

    private readonly MethodLocator $locator;

    /**
     * A scan for one read, in a scope whose `$this` is an instance of $runtime.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    private function __construct(
        ReadPath $read,
        private readonly bool $proxied,
        private readonly ReflectionClass $runtime,
    ) {
        $this->readSteps = $this->steps($read);
        $this->references = resolve(ByReferenceArguments::class);
        $this->locator = resolve(MethodLocator::class);
    }

    /**
     * Whether a window's items may change what the read holds: through a write any item spells or an argument it binds
     * by reference, or through a call any item but the last makes.
     *
     * @param  list<ArrayItem>  $items  the window: the group's first key up to the last item that tests the read
     */
    public static function reach(array $items, ReadPath $read, AnalysisScope $scope): bool
    {
        $scan = new self($read, $scope->hasResourceProxy(), $scope->runtime->class);
        $last = array_key_last($items);

        return array_any($items, static fn (ArrayItem $item, int $index): bool => $scan->mayChange(
            [$item],
            $scope->declaringClass,
            0,
            $index !== $last,
        ));
    }

    /**
     * The paths a node writes, each as far as a path names it: `$a->b[0] = …` writes `$a->b`, and `unset()` each
     * path it removes.
     *
     * @return list<ReadPath>
     */
    private static function writtenPaths(Node $node): array
    {
        $targets = match (true) {
            $node instanceof Assign, $node instanceof AssignOp, $node instanceof AssignRef, $node instanceof PreInc,
            $node instanceof PreDec, $node instanceof PostInc, $node instanceof PostDec => [$node->var],
            $node instanceof Unset_ => $node->vars,
            default => [],
        };

        return array_merge(...array_map(self::targetPaths(...), $targets));
    }

    /**
     * The paths one write target names: the longest path it starts with, or each of a destructuring's targets.
     *
     * @return list<ReadPath>
     */
    private static function targetPaths(Expr $target): array
    {
        if ($target instanceof List_ || $target instanceof Array_) {
            return array_merge(...array_map(
                static fn (?ArrayItem $item): array => $item === null ? [] : self::targetPaths($item->value),
                $target->items,
            ));
        }

        $path = ReadPath::of($target);

        while ($path === null && ($target instanceof ArrayDimFetch || $target instanceof PropertyFetch
            || $target instanceof NullsafePropertyFetch)) {
            $target = $target->var;
            $path = ReadPath::of($target);
        }

        return $path === null ? [] : [$path];
    }

    /**
     * The path an object read names, or the longest one it starts with where a member's name is computed, since that
     * member may be any of them.
     */
    private static function objectPath(Expr $expr): ?ReadPath
    {
        $path = ReadPath::of($expr);

        while ($path === null && ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch)) {
            $expr = $expr->var;
            $path = ReadPath::of($expr);
        }

        return $path;
    }

    /**
     * Whether an expression is `$this`.
     */
    private static function isThis(Node $node): bool
    {
        return $node instanceof Variable && $node->name === 'this';
    }

    /**
     * Whether a value is a closure or an arrow function written there, whose body the scan reads in place.
     */
    private static function isClosure(?Expr $value): bool
    {
        return $value instanceof ClosureExpr || $value instanceof ArrowFunction;
    }

    /**
     * Whether a value can hold no closure: a scalar, a constant, an array literal, a `new`, `$this` or a property read.
     */
    private static function holdsNoClosure(Expr $value): bool
    {
        return $value instanceof Scalar || $value instanceof ConstFetch || $value instanceof ClassConstFetch
            || $value instanceof Array_ || $value instanceof New_ || self::isThis($value)
            // A property is taken to hold data: following a closure one holds needs every write to it, a known gap.
            || (($value instanceof PropertyFetch || $value instanceof NullsafePropertyFetch)
                && ReadPath::of($value) !== null);
    }

    /**
     * Whether nodes may change the read through a write they spell or an argument they bind by reference, or, where
     * $calls holds, through code they run.
     *
     * @param  array<Node>  $nodes
     * @param  ReflectionClass<object>  $declaring  the class `self` names in them
     * @param  int  $depth  how many `$this` method bodies down they sit, none for the window's items
     */
    private function mayChange(array $nodes, ReflectionClass $declaring, int $depth, bool $calls): bool
    {
        return new NodeFinder()->findFirst($nodes, fn (Node $node): bool => $this->writes($node, $depth)
            || ($node instanceof CallLike && $this->bindsByReference($node, $declaring, $depth))
            || ($calls && ($node instanceof CallLike
                ? $this->callMayWrite($node, $declaring, $depth)
                : $this->runsUnseen($node)))) !== null;
    }

    /**
     * Whether a node spells a write that reaches the read.
     */
    private function writes(Node $node, int $depth): bool
    {
        return array_any(self::writtenPaths($node), fn (ReadPath $written): bool => $this->reaches($written, $depth));
    }

    /**
     * Whether a call binds the read, or a path it is a member of, to a by-reference parameter of the callee it names.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function bindsByReference(CallLike $call, ReflectionClass $declaring, int $depth): bool
    {
        return ! $call->isFirstClassCallable() && array_any(
            $this->references->of($call, $call->getArgs(), $declaring, $this->runtime),
            fn (Expr $bound): bool => array_any(
                self::targetPaths($bound),
                fn (ReadPath $path): bool => $this->reaches($path, $depth),
            ),
        );
    }

    /**
     * Whether a call may change the read, by the callee it runs: a method run on `$this`, a method of an object, or a
     * function, another class's static method or a constructor through what it is handed. A first-class callable
     * runs nothing until something calls it.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callMayWrite(CallLike $call, ReflectionClass $declaring, int $depth): bool
    {
        if ($call->isFirstClassCallable()) {
            return false;
        }

        if ($this->runsUnseen($call)) {
            return true;
        }

        $onThis = $this->thisMethod($call, $declaring);

        if ($onThis !== null) {
            return $this->thisCallMayWrite($call, $onThis[0], $onThis[1], $declaring, $depth);
        }

        return ($call instanceof MethodCall || $call instanceof NullsafeMethodCall) && $call->name instanceof Identifier
            ? $this->objectCallMayWrite($call, $call->name->toString(), $declaring, $depth)
            : ! $this->knownToRead($call, $declaring) && $this->handsSubject($call, $declaring, $depth);
    }

    /**
     * Whether a node runs code no scan follows: `eval`, `include`, a call of a value or by a name the source does not
     * spell, `__invoke()` of anything but a first-class callable, or call_user_func() handed anything but a closure
     * written there.
     */
    private function runsUnseen(Node $node): bool
    {
        return match (true) {
            $node instanceof Eval_, $node instanceof Include_ => true,
            ! $node instanceof CallLike || $node->isFirstClassCallable() => false,
            $node instanceof FuncCall => ! $node->name instanceof Name
                || (in_array($node->name->toLowerString(), self::CALLERS, true)
                    && ! self::isClosure($node->getArgs()[0]->value ?? null)),
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => ! $node->name instanceof Identifier
                || ($node->name->toLowerString() === '__invoke'
                    && ! ($node->var instanceof CallLike && $node->var->isFirstClassCallable())),
            $node instanceof StaticCall => ! $node->name instanceof Identifier
                || ! ($node->class instanceof Name || self::isThis($node->class)),
            default => false,
        };
    }

    /**
     * Whether a call PHP runs on `$this` may change the read: a resource helper through the callbacks it calls, and
     * any other through what it is handed and the method it runs.
     *
     * @param  ReflectionClass<object>  $class  the class whose method the call runs
     * @param  ReflectionClass<object>  $declaring
     */
    private function thisCallMayWrite(
        CallLike $call,
        ReflectionClass $class,
        string $method,
        ReflectionClass $declaring,
        int $depth,
    ): bool {
        $helper = ReadOnlyCalls::helperOn($class, $method);

        return match (true) {
            $helper !== null => $this->callbacksMayWrite(CallArguments::for($call, $helper), $declaring, $depth),
            $class->hasMethod($method) && $this->handsSubject($call, $declaring, $depth) => true,
            default => $this->methodMayWrite($class, $method, $depth),
        };
    }

    /**
     * Whether running a method on `$this` may change the read: through its body, or, where the class lacks it, as the
     * model's method an API resource forwards it to.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function methodMayWrite(ReflectionClass $class, string $method, int $depth): bool
    {
        return $class->hasMethod($method)
            ? $this->bodyMayWrite($class, $method, $depth)
            : ! $this->proxied || $this->modelCallMayWrite($method);
    }

    /**
     * Whether the body of a method run on `$this` may change the read, read DEPTH levels down at most; a body any
     * deeper, or one the locator cannot read, may. A body read already, or being read, counts where it is read, since
     * a body that may change the read ends the whole scan: no caller may drop that answer.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function bodyMayWrite(ReflectionClass $class, string $method, int $depth): bool
    {
        $key = $class->getName().'::'.strtolower($method);

        if (isset($this->bodies[$key])) {
            return false;
        }

        $body = $depth < self::DEPTH ? $this->locator->locate($class->getName(), $method)?->method->stmts : null;

        if ($body === null) {
            return true;
        }

        $this->bodies[$key] = true;

        return $this->mayChange($body, $class->getMethod($method)->getDeclaringClass(), $depth + 1, true);
    }

    /**
     * Whether a callback a resource helper calls may change the read; the helper only reads its other arguments, and
     * calls a callback only where it holds a closure.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callbacksMayWrite(CallArguments $arguments, ReflectionClass $declaring, int $depth): bool
    {
        return $arguments->hasUnpack() || array_any(
            ReadOnlyCalls::callbacks($arguments),
            fn (Expr $callback): bool => $this->callbackMayWrite($callback, $declaring, $depth),
        );
    }

    /**
     * Whether one callback a helper calls may change the read. It is safe only where it holds no closure, is a closure
     * the scan reads in place, a first-class callable of a method whose body may not change the read, or a call whose
     * callee's return type holds no closure, or yields only such values; anything else may.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callbackMayWrite(Expr $callback, ReflectionClass $declaring, int $depth): bool
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
     * Whether calling a first-class callable may change the read where only a method it names is read: through that
     * method's body, and any other may.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function methodCallableMayWrite(CallLike $callable, ReflectionClass $declaring, int $depth): bool
    {
        $target = $this->callableTarget($callable, $declaring);

        return ! is_array($target) || $this->methodMayWrite($target[0], $target[1], $depth);
    }

    /**
     * Whether calling a callable value may change the read: one naming a method run on `$this`, or another class's
     * static method, through that method (one it cannot name may), one bound to an object holding the read as the
     * model's method; any other binds nothing of the subject.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function runMayWrite(Expr $callable, ReflectionClass $declaring, int $depth): bool
    {
        $target = $this->callableTarget($callable, $declaring);

        return match (true) {
            is_array($target) => $this->methodMayWrite($target[0], $target[1], $depth),
            $target === false => true,
            default => ($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && $this->holdsRead(self::objectPath($callable->var), $depth)
                && (! $callable->name instanceof Identifier || $this->modelCallMayWrite($callable->name->toString())),
        };
    }

    /**
     * Whether a call's callee may return a closure: one this cannot name, or whose return type is none or one
     * CLOSURE_HOLDERS lists.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function mayReturnClosure(CallLike $call, ReflectionClass $declaring): bool
    {
        $onThis = $this->thisMethod($call, $declaring);
        $callee = $onThis === null
            ? $this->references->callee($call, $declaring, $this->runtime)
            : ($onThis[0]->hasMethod($onThis[1]) ? $onThis[0]->getMethod($onThis[1]) : null);
        $type = $callee?->getReturnType();

        return $type === null || array_intersect(
            self::CLOSURE_HOLDERS,
            preg_split('/[|&()?\s]+/', strtolower((string) $type), flags: PREG_SPLIT_NO_EMPTY) ?: [],
        ) !== [];
    }

    /**
     * Whether a method call on an object may change the read: on a first-class callable, as a call of that callable;
     * on one holding the read, as the model's method; on the read itself or anything else only through what it is
     * handed.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function objectCallMayWrite(
        MethodCall|NullsafeMethodCall $call,
        string $method,
        ReflectionClass $declaring,
        int $depth,
    ): bool {
        if ($call->var instanceof CallLike && $call->var->isFirstClassCallable()) {
            return $this->runMayWrite($call->var, $declaring, $depth) || $this->handsSubject($call, $declaring, $depth);
        }

        return $this->holdsRead(self::objectPath($call->var), $depth)
            ? $this->modelCallMayWrite($method)
            : $this->handsSubject($call, $declaring, $depth);
    }

    /**
     * Whether a model method may change the model: any that Eloquent does not list as only reading it.
     */
    private function modelCallMayWrite(string $method): bool
    {
        return ! ReadOnlyCalls::readsModel($method);
    }

    /**
     * Whether a call is one the framework only reads with: `route()` or `url()`, a Gate check, or `new`, make() or
     * collection() of a resource with JsonResource's own constructor.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function knownToRead(CallLike $call, ReflectionClass $declaring): bool
    {
        $built = $call instanceof New_ && $call->class instanceof Name
            ? ($call->class->isSpecialClassName()
                ? AnalysisScope::relativeClassOf($call->class, $declaring, $this->runtime)?->getName()
                : $call->class->toString())
            : null;

        return match (true) {
            $call instanceof FuncCall => ReadOnlyCalls::urlHelper($call),
            $call instanceof StaticCall => ReadOnlyCalls::gateCheck($call)
                || ReadOnlyCalls::buildsResourceStatically($call),
            default => $built !== null && ReadOnlyCalls::buildsResource($built),
        };
    }

    /**
     * Whether a call hands its callee the subject, an object the read is a member of, or a callable that may change
     * the read, in one of its arguments.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function handsSubject(CallLike $call, ReflectionClass $declaring, int $depth): bool
    {
        return ! $call->isFirstClassCallable()
            && array_any($call->getArgs(), fn (Arg $arg): bool => $this->hands($arg->value, $declaring, $depth));
    }

    /**
     * Whether a value may be the subject or an object the read is a member of, or a callable that may change the read
     * when the callee calls it: such a path or callable, or an array, a branch, an assignment or a closure's return
     * that may hold one.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function hands(?Expr $value, ReflectionClass $declaring, int $depth): bool
    {
        if ($value === null) {
            return false;
        }

        if (($value instanceof CallLike && $value->isFirstClassCallable())
            || $this->callableTarget($value, $declaring) !== null
        ) {
            return $this->runMayWrite($value, $declaring, $depth);
        }

        $holds = fn (?Expr $part): bool => $this->hands($part, $declaring, $depth);

        return match (true) {
            $value instanceof Variable, $value instanceof PropertyFetch,
            $value instanceof NullsafePropertyFetch => $this->holdsRead(self::objectPath($value), $depth),
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
     * The class and method a callable value names: a first-class callable of a method run on `$this` or of another
     * class's static method, `[$this, 'm']`, or `'static::m'` and its kin; false where it names one by a name the
     * source does not spell or no class here declares; null where it names no method of this kind.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|false|null
     */
    private function callableTarget(Expr $callable, ReflectionClass $declaring): array|false|null
    {
        $relative = ReadOnlyCalls::relativeCallable($callable);

        if ($relative !== null) {
            $class = AnalysisScope::relativeClassOf(new Name($relative[0]), $declaring, $this->runtime);

            return $class === null || $relative[1] === null ? false : [$class, $relative[1]];
        }

        if ($callable instanceof Array_) {
            $method = count($callable->items) === 2 && self::isThis($callable->items[0]->value)
                ? $callable->items[1]->value
                : null;

            return match (true) {
                $method instanceof String_ => [
                    AnalysisScope::memberClassOf($declaring, $this->runtime, $method->value, true),
                    $method->value,
                ],
                $method === null => null,
                default => false,
            };
        }

        if (! $callable instanceof CallLike || ! $callable->isFirstClassCallable() || $callable instanceof FuncCall
            || (($callable instanceof MethodCall || $callable instanceof NullsafeMethodCall)
                && ! self::isThis($callable->var))
        ) {
            return null;
        }

        [$class, $method] = $callable instanceof StaticCall && $callable->class instanceof Name
            && $callable->name instanceof Identifier
            ? [$callable->class->toString(), $callable->name->toString()]
            : ['', ''];

        return $this->thisMethod($callable, $declaring)
            ?? (class_exists($class) && method_exists($class, $method)
                ? [new ReflectionClass($class), $method]
                : false);
    }

    /**
     * The class and name of the method a call runs on `$this`: through `$this->`, `$this::`, `self::`, `static::` or
     * `parent::`, or an ancestor's name for a method PHP runs on `$this`; null for any other call.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return CallableTarget|null
     */
    private function thisMethod(CallLike $call, ReflectionClass $declaring): ?array
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

    /**
     * Whether a path is the subject or an object the read is a member of, where it names the variable the read does.
     */
    private function holdsRead(?ReadPath $path, int $depth): bool
    {
        if ($path === null || ! $this->inScope($path, $depth)) {
            return false;
        }

        $held = $this->steps($path);

        return count($held) < count($this->readSteps) && array_slice($this->readSteps, 0, count($held)) === $held;
    }

    /**
     * Whether a write to a path reaches the read: the path is the read, or one the read is a member of at any depth,
     * whatever operator each step is read with, since PHP writes through `->` alone.
     */
    private function reaches(ReadPath $written, int $depth): bool
    {
        $target = $this->steps($written);

        return $this->inScope($written, $depth) && array_slice($this->readSteps, 0, count($target)) === $target;
    }

    /**
     * Whether a path's root is the variable the read's is: any in the window, only `$this` in a method body.
     */
    private function inScope(ReadPath $path, int $depth): bool
    {
        return $depth === 0 || $path->root === 'this';
    }

    /**
     * A path's root and segments, with `$this->resource->` read as `$this->` where an API resource forwards one to
     * the other.
     *
     * @return list<string>
     */
    private function steps(ReadPath $path): array
    {
        $segments = $this->proxied && $path->root === 'this' && ($path->segments[0] ?? null) === 'resource'
            ? array_slice($path->segments, 1)
            : $path->segments;

        return [$path->root, ...$segments];
    }
}
