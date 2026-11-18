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
use PhpParser\Node\Expr\Closure as ClosureExpr;
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
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;
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
 * @internal
 */
final class InterveningWrites
{
    /** How many levels of `$this` method bodies one scan reads; a body any deeper may write. */
    private const int DEPTH = 3;

    /** The built-ins that call the callable they are given first. */
    private const array CALLERS = ['call_user_func', 'call_user_func_array'];

    /** @var array<string, true> the `$this` method bodies read, or being read, by class and lowercased name */
    private array $bodies = [];

    /** @var list<string> the read's steps, as steps() spells them */
    private readonly array $readSteps;

    /** Whether the read is a member of `$this`, so whatever `$this` is handed to may write it. */
    private readonly bool $readsThis;

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
        $this->readsThis = $this->readSteps[0] === 'this' && count($this->readSteps) > 1;
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
            ? $this->objectCallMayWrite($call, $call->name->toString(), $depth)
            : ! $this->knownToRead($call, $declaring) && $this->handsSubject($call, $depth);
    }

    /**
     * Whether a node runs code no scan follows: `eval`, `include`, a call of a value or by a name the source does not
     * spell, or call_user_func() handed anything but a closure written there.
     */
    private function runsUnseen(Node $node): bool
    {
        return match (true) {
            $node instanceof Eval_, $node instanceof Include_ => true,
            ! $node instanceof CallLike || $node->isFirstClassCallable() => false,
            $node instanceof FuncCall => ! $node->name instanceof Name
                || (in_array($node->name->toLowerString(), self::CALLERS, true)
                    && ! self::isClosure($node->getArgs()[0]->value ?? null)),
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => ! $node->name instanceof Identifier,
            $node instanceof StaticCall => ! $node->name instanceof Identifier
                || ! ($node->class instanceof Name || self::isThis($node->class)),
            default => false,
        };
    }

    /**
     * Whether a call PHP runs on `$this` may change the read: a resource helper through the callbacks it calls, a
     * method the class lacks as the model's method it forwards to, and any other through what it is handed and its
     * body.
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
            ! $class->hasMethod($method) => ! $this->proxied || $this->modelCallMayWrite($method),
            default => $this->handsSubject($call, $depth) || $this->bodyMayWrite($class, $method, $depth),
        };
    }

    /**
     * Whether the body of a method run on `$this` may change the read, read DEPTH levels down at most; a body any
     * deeper, or one the locator cannot read, may. A body read already, or being read, counts where it is read.
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
     * Whether a callback a resource helper calls may change the read: a closure written there through its body, which
     * the scan reads in place, a first-class callable of a `$this` method through that method, and any other value
     * that may hold a closure may; the helper only reads its other arguments.
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
     * Whether one callback a helper calls may change the read; see callbacksMayWrite().
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callbackMayWrite(Expr $callback, ReflectionClass $declaring, int $depth): bool
    {
        return match (true) {
            $callback instanceof CallLike => $callback->isFirstClassCallable()
                && $this->callableMayWrite($callback, $declaring, $depth),
            $callback instanceof Variable => ! self::isThis($callback),
            $callback instanceof StaticPropertyFetch => true,
            $callback instanceof Ternary => $this->callbackMayWrite($callback->if ?? $callback->cond, $declaring, $depth)
                || $this->callbackMayWrite($callback->else, $declaring, $depth),
            $callback instanceof Coalesce => $this->callbackMayWrite($callback->left, $declaring, $depth)
                || $this->callbackMayWrite($callback->right, $declaring, $depth),
            $callback instanceof Match_ => array_any(
                $callback->arms,
                fn (MatchArm $arm): bool => $this->callbackMayWrite($arm->body, $declaring, $depth),
            ),
            default => false,
        };
    }

    /**
     * Whether calling a first-class callable may change the read: one of a method run on `$this` as a call of that
     * method, any other may.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function callableMayWrite(CallLike $callable, ReflectionClass $declaring, int $depth): bool
    {
        $onThis = $this->thisMethod($callable, $declaring);

        return $onThis === null || $this->thisCallMayWrite($callable, $onThis[0], $onThis[1], $declaring, $depth);
    }

    /**
     * Whether a method call on an object may change the read: on one holding the read, as the model's method, and on
     * the read itself or anything else only through what it is handed.
     */
    private function objectCallMayWrite(MethodCall|NullsafeMethodCall $call, string $method, int $depth): bool
    {
        return $this->holdsRead(self::objectPath($call->var), $depth)
            ? $this->modelCallMayWrite($method)
            : $this->handsSubject($call, $depth);
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
     * Whether a call hands its callee the subject, or an object the read is a member of, in one of its arguments.
     */
    private function handsSubject(CallLike $call, int $depth): bool
    {
        return ! $call->isFirstClassCallable()
            && array_any($call->getArgs(), fn (Arg $arg): bool => $this->hands($arg->value, $depth));
    }

    /**
     * Whether a value may be the subject or an object the read is a member of: such a path, a callable bound to one,
     * or an array, a branch, an assignment or a closure's return that may hold one.
     */
    private function hands(?Expr $value, int $depth): bool
    {
        return match (true) {
            $value instanceof Variable, $value instanceof PropertyFetch, $value instanceof NullsafePropertyFetch => $this->holdsRead(self::objectPath($value), $depth),
            $value instanceof MethodCall, $value instanceof NullsafeMethodCall => $value->isFirstClassCallable()
                && $this->hands($value->var, $depth),
            $value instanceof StaticCall => $value->isFirstClassCallable() && ($value->class instanceof Name
                ? $value->class->isSpecialClassName() && $this->readsThis
                : $this->hands($value->class, $depth)),
            $value instanceof Array_ => array_any(
                $value->items,
                fn (ArrayItem $item): bool => $this->hands($item->value, $depth),
            ),
            $value instanceof Ternary => $this->hands($value->if ?? $value->cond, $depth)
                || $this->hands($value->else, $depth),
            $value instanceof Coalesce => $this->hands($value->left, $depth) || $this->hands($value->right, $depth),
            $value instanceof Assign, $value instanceof AssignRef => $this->hands($value->expr, $depth),
            $value instanceof Match_ => array_any(
                $value->arms,
                fn (MatchArm $arm): bool => $this->hands($arm->body, $depth),
            ),
            $value instanceof ArrowFunction => $this->hands($value->expr, $depth),
            $value instanceof ClosureExpr => array_any(
                new NodeFinder()->findInstanceOf($value->stmts, Return_::class),
                fn (Return_ $return): bool => $this->hands($return->expr, $depth),
            ),
            default => false,
        };
    }

    /**
     * The class and name of the method a call runs on `$this`: through `$this->`, `$this::`, `self::`, `static::` or
     * `parent::`, or an ancestor's name for a method PHP runs on `$this`; null for any other call.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return array{ReflectionClass<object>, string}|null
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
