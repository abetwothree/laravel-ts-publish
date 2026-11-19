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
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp\Pipe;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\List_;
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
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionParameter;

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
    /** The built-ins that call the callable they are given first. */
    private const array CALLERS = ['call_user_func', 'call_user_func_array'];

    /** @var list<string> the read's steps, as steps() spells them */
    private readonly array $readSteps;

    private readonly ByReferenceArguments $references;

    private readonly BodyReads $reads;

    private readonly CallableTargets $targets;

    private readonly ClosureReach $closures;

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
        $this->reads = new BodyReads(resolve(MethodLocator::class));
        $this->targets = new CallableTargets($runtime);
        $this->closures = new ClosureReach(
            $this->targets,
            $this->reads,
            $runtime,
            $this->methodMayWrite(...),
            $this->holdsObject(...),
        );
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
     * Whether a parameter's declared type is or holds `callable` or `Closure`.
     */
    private static function declaresCallable(ReflectionParameter $parameter): bool
    {
        $names = preg_split('/[|&()?\s]+/', strtolower((string) $parameter->getType()), flags: PREG_SPLIT_NO_EMPTY);

        return array_intersect(['callable', 'closure'], $names ?: []) !== [];
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
            || ($calls && match (true) {
                $node instanceof CallLike => $this->callMayWrite($node, $declaring, $depth),
                $node instanceof Pipe => $this->pipeMayWrite($node, $declaring, $depth),
                default => $this->runsUnseen($node),
            })) !== null;
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

        $onThis = $this->targets->thisMethod($call, $declaring);

        if ($onThis !== null) {
            return $this->thisCallMayWrite($call, $onThis[0], $onThis[1], $declaring, $depth);
        }

        return ($call instanceof MethodCall || $call instanceof NullsafeMethodCall) && $call->name instanceof Identifier
            ? $this->objectCallMayWrite($call, $call->name->toString(), $declaring, $depth)
            : ! $this->knownToRead($call, $declaring) && $this->handsSubject($call, $declaring, $depth);
    }

    /**
     * Whether a pipe may change the read: it calls its right side with its left handed to it.
     *
     * @param  ReflectionClass<object>  $declaring
     */
    private function pipeMayWrite(Pipe $pipe, ReflectionClass $declaring, int $depth): bool
    {
        return $this->closures->hands($pipe->left, $declaring, $depth, false)
            || $this->closures->mayRun($pipe->right, ValueUse::Called, $declaring, $depth);
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
                    && ! ClosureReach::isClosure($node->getArgs()[0]->value ?? null)),
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => ! $node->name instanceof Identifier
                || ($node->name->toLowerString() === '__invoke'
                    && ! ($node->var instanceof CallLike && $node->var->isFirstClassCallable())),
            $node instanceof StaticCall => ! $node->name instanceof Identifier
                || ! ($node->class instanceof Name || CallableTargets::isThis($node->class)),
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
     * Whether the body of a method run on `$this` may change the read, as BodyReads reads it.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function bodyMayWrite(ReflectionClass $class, string $method, int $depth): bool
    {
        return $this->reads->mayWrite(
            'body',
            $class,
            $method,
            $depth,
            fn (array $body, ReflectionClass $declaring, int $inner): bool => $this->mayChange(
                $body,
                $declaring,
                $inner,
                true,
            ),
        );
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
            fn (Expr $callback): bool => $this->closures->mayRun($callback, ValueUse::Callback, $declaring, $depth),
        );
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
            return $this->closures->mayRun($call->var, ValueUse::Called, $declaring, $depth)
                || $this->handsSubject($call, $declaring, $depth);
        }

        return $this->holdsObject($call->var, $depth)
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
        if ($call->isFirstClassCallable() || $call->getArgs() === []) {
            return false;
        }

        $run = $this->builtInCallables($call, $declaring);

        return array_any(
            $call->getArgs(),
            fn (Arg $arg): bool => $this->closures->hands($arg->value, $declaring, $depth, in_array($arg, $run, true)),
        );
    }

    /**
     * The arguments a call binds to a parameter its callee declares `callable` or `Closure`, where PHP itself
     * implements the callee, which may run them but sets no property of them.
     *
     * @param  ReflectionClass<object>  $declaring
     * @return list<Arg>
     */
    private function builtInCallables(CallLike $call, ReflectionClass $declaring): array
    {
        $callee = $this->references->callee($call, $declaring, $this->runtime);

        if ($callee === null || ! $callee->isInternal()) {
            return [];
        }

        $arguments = CallArguments::for($call, $callee);

        return array_values(array_filter(array_map(
            static fn (ReflectionParameter $parameter): ?Arg => self::declaresCallable($parameter)
                ? $arguments->named($parameter->getName())
                : null,
            $callee->getParameters(),
        )));
    }

    /**
     * Whether an object read is the subject or an object the read is a member of; see holdsRead().
     */
    private function holdsObject(Expr $object, int $depth): bool
    {
        return $this->holdsRead(self::objectPath($object), $depth);
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
