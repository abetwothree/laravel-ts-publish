<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\CollectsResources;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Whether a chain of property reads on `$this` may stop holding what an `instanceof` test proved, between the test
 * and its reads: a write to the chain or one it reads through, a write through a local or to one bound by reference,
 * code no scan sees, or any call but those known not to reach it (safeCall()). Any of these ends the proof.
 *
 * On an API resource a chain the subject does not declare reads through `$this->resource`.
 *
 * @phpstan-type Parameters = array<string, list<string>|null>
 *
 * @phpstan-import-type NamedWrite from VariableWrites
 * @phpstan-import-type NamedWrites from VariableWrites
 *
 * @internal
 */
final class ThisPathWrites
{
    /** The framework's resource classes and traits, whose helpers only read the resource. */
    private const array READING_DECLARERS = [
        JsonResource::class, ResourceCollection::class, ConditionallyLoadsAttributes::class, CollectsResources::class,
    ];

    /** The JsonResource helpers that read what they are given, calling a closure given at most, lowercased. */
    private const array RESOURCE_HELPERS = [
        'when', 'unless', 'whenloaded', 'whennotnull', 'whennull', 'whenhas', 'whencounted', 'whenaggregated',
        'whenpivotloaded', 'whenpivotloadedas', 'whenexistsloaded', 'mergewhen', 'mergeunless', 'merge',
    ];

    /** The helpers that call their callback with a member of the resource, lowercased. */
    private const array HANDING_HELPERS = ['whenloaded', 'whenhas', 'whencounted', 'whenaggregated', 'whenexistsloaded'];

    /** The parameters of a JsonResource helper whose closure the helper calls. */
    private const array CALLBACK_PARAMETERS = ['value', 'default'];

    /** The ability checks the signed-in user runs through the app's gates, lowercased. */
    private const array ABILITY_CHECKS = ['can', 'cannot', 'canany'];

    /** The Gate facade's checks, which run the app's gates and policies. */
    private const array GATE_CHECKS = ['allows', 'denies', 'check', 'any', 'none'];

    /** The helpers that only read a model to build a URL. */
    private const array URL_HELPERS = ['route', 'url'];

    /** The built-ins that call the callable they are given first. */
    private const array CALLERS = ['call_user_func', 'call_user_func_array'];

    /** @var array{list<array{CallLike, Parameters}>, list<Unset_>, list<Node>}|null */
    private ?array $nodes = null;

    /** @var array<string, true>|null */
    private ?array $references = null;

    /**
     * Reads a method body's proofs against the writes and calls it holds, in the scope that reads the body.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  NamedWrites  $writes  the body's writes, as VariableWrites::of() scans them
     */
    public function __construct(private readonly array $stmts, private readonly array $writes, private readonly AnalysisScope $scope) {}

    /**
     * Whether something that may change what the chain a proof key names holds can run between two offsets: one that
     * does not end before $from, nor start past $to when it is set, and that sits in none of $exits. A write through
     * the chain passes when it keeps every typed member of $classes, as one through a variable proven to hold them.
     *
     * @param  list<array<Node\Stmt>>  $exits
     * @param  non-empty-list<class-string>  $classes
     */
    public function reach(string $key, int $from, ?int $to, array $exits, array $classes): bool
    {
        $path = $this->canonical(explode('->', substr($key, strlen('$this->'))));

        foreach ($this->writes as $write) {
            [$name, $node, $target, $kind] = $write;

            // A `foreach` around the test assigns its value before each pass, so never between the test and a read.
            if (($kind === VariableWrite::Foreach && $node->getStartFilePos() < $from) || ! $this->lands($node, $from, $to, $exits)) {
                continue;
            }

            // A local may hold the chain, so a write through one may write the chain, as may any write to one bound by
            // reference; unset() only breaks the reference.
            if ($name === 'this'
                ? ! $this->keepsWrite($write, $path, $classes)
                : self::writesThrough($target) || ($kind !== VariableWrite::Unsets && $this->bindsByReference($name))
            ) {
                return true;
            }
        }

        [$calls, $unsets, $unseen] = $this->nodes();

        return array_any($unseen, fn (Node $node): bool => $this->lands($node, $from, $to, $exits))
            || array_any($unsets, fn (Unset_ $unset): bool => $this->lands($unset, $from, $to, $exits) && $this->unsetChanges($unset, $path, $classes))
            || array_any($calls, fn (array $call): bool => $this->lands($call[0], $from, $to, $exits) && ! $this->safeCall($call[0], $path, $call[1]));
    }

    /**
     * The names a written chain goes past the proven one by: none where it is that chain or one the chain reads
     * through, null where neither spells the start of the other.
     *
     * @param  list<string>  $written
     * @param  list<string>  $path
     * @return list<string>|null
     */
    private static function through(array $written, array $path): ?array
    {
        $common = min(count($written), count($path));

        return array_slice($written, 0, $common) === array_slice($path, 0, $common) ? array_slice($written, $common) : null;
    }

    /**
     * Whether a write target goes through the variable it writes, as a member or key of it, not the variable itself.
     */
    private static function writesThrough(Expr $target): bool
    {
        return match (true) {
            $target instanceof Variable => false,
            $target instanceof List_, $target instanceof Array_ => array_any(
                $target->items,
                fn (?Node\ArrayItem $item): bool => $item !== null && self::writesThrough($item->value),
            ),
            default => true,
        };
    }

    /**
     * The root of a chain of property and key reads.
     */
    private static function rootOf(Expr $expr): Expr
    {
        while ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch || $expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        return $expr;
    }

    /**
     * Whether a name runs one of Laravel's global helpers: no namespaced function of that name shadows it, and the
     * global one is the framework's.
     *
     * @param  list<string>  $helpers
     */
    private static function runsLaravelHelper(Name $name, array $helpers): bool
    {
        $namespaced = $name->getAttribute('namespacedName');

        if (! in_array($name->toLowerString(), $helpers, true)
            || ($namespaced instanceof Name && function_exists($namespaced->toString()))
            || ! function_exists($name->toString())
        ) {
            return false;
        }

        $file = str_replace('\\', '/', (string) new ReflectionFunction($name->toString())->getFileName());

        return str_ends_with($file, '/Illuminate/Foundation/helpers.php');
    }

    /**
     * Whether a chain is one the proven chain reads through, short of the proven chain itself.
     *
     * @param  list<string>  $chain
     * @param  list<string>  $path
     */
    private static function strictPrefix(array $chain, array $path): bool
    {
        return $chain !== $path && self::through($chain, $path) === [];
    }

    /**
     * Whether a chain apart from the proven one writes a member the proven chain reads past its first, which may be the
     * same object's, as two `$this` chains can hold one.
     *
     * @param  list<string>  $written
     * @param  list<string>  $path
     */
    private static function mayShareObject(array $written, array $path): bool
    {
        return count($written) > 1 && in_array($written[count($written) - 1], array_slice($path, 1), true);
    }

    /**
     * Whether a destructuring target binds the variable by reference: `[&$ref] = …`, at any depth.
     */
    private static function destructuresByReference(Expr $target, string $name): bool
    {
        return ($target instanceof List_ || $target instanceof Array_) && array_any(
            $target->items,
            fn (?Node\ArrayItem $item): bool => $item !== null && (($item->byRef && $item->value instanceof Variable
                && $item->value->name === $name) || self::destructuresByReference($item->value, $name)),
        );
    }

    /**
     * The parameters in scope inside a closure: those of the closures around it, then its own, each holding nothing of
     * the subject but the first one $handed names, which stands for that chain, or for anything where $handed is false
     * or the parameter is variadic.
     *
     * @param  Parameters  $outer
     * @param  list<string>|false|null  $handed  the member a helper hands the closure, as the chain it reads
     * @return Parameters
     */
    private static function closureParameters(ClosureExpr|ArrowFunction $closure, array $outer, array|false|null $handed): array
    {
        foreach ($closure->params as $position => $param) {
            if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $name = $param->var->name;
            unset($outer[$name]);

            if ($position > 0 || $handed === null) {
                $outer[$name] = null;
            } elseif ($handed !== false && ! $param->variadic) {
                $outer[$name] = $handed;
            }
        }

        return $outer;
    }

    /**
     * Whether a node runs code or names a variable no scan can read: `eval`, `include` or `require`, or a variable
     * variable.
     */
    private static function runsUnseen(Node $node): bool
    {
        return $node instanceof Eval_ || $node instanceof Include_ || ($node instanceof Variable && ! is_string($node->name));
    }

    /**
     * Whether a call writes the variables of the scope it runs in: extract(), or parse_str() given no array to fill.
     */
    private static function writesScope(Node $node): bool
    {
        if (! $node instanceof FuncCall || ! $node->name instanceof Name || $node->isFirstClassCallable()) {
            return false;
        }

        $name = $node->name->toLowerString();

        return $name === 'extract' || ($name === 'parse_str' && count($node->getArgs()) < 2);
    }

    /**
     * Whether a body may write one of its variables by a name no scan reads, outside the closures it holds, which have
     * variables of their own.
     *
     * @param  array<Node>  $stmts
     */
    private static function writesUnseen(array $stmts): bool
    {
        $stack = $stmts;

        while ($stack !== []) {
            $node = array_pop($stack);

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_
                || $node instanceof ClosureExpr || $node instanceof ArrowFunction
            ) {
                continue;
            }

            if (self::runsUnseen($node) || self::writesScope($node)) {
                return true;
            }

            foreach ($node->getSubNodeNames() as $name) {
                array_push($stack, ...(is_array($node->{$name}) ? $node->{$name} : [$node->{$name}]));
            }
        }

        return false;
    }

    /**
     * Whether a value names a callable other than a closure written there: a first-class callable,
     * Closure::fromCallable(), a function's or a static method's name, or a class-and-method array.
     */
    private static function namesCallable(Expr $value): bool
    {
        $items = $value instanceof Array_ ? $value->items : [];
        $class = isset($items[0]) ? $items[0]->value : null;

        return match (true) {
            $value instanceof CallLike && $value->isFirstClassCallable() => true,
            $value instanceof StaticCall => $value->class instanceof Name && $value->class->toLowerString() === 'closure'
                && $value->name instanceof Identifier && $value->name->toLowerString() === 'fromcallable',
            $value instanceof String_ => str_contains($value->value, '::') || function_exists($value->value),
            default => count($items) === 2 && isset($items[1]) && $items[1]->value instanceof String_ && (self::isClassName($class)
                || ($class instanceof String_ && class_exists($class->value))),
        };
    }

    /**
     * Whether a value spells a callable relative to the subject, which runs on `$this`: `'static::m'` or
     * `['parent', 'm']`.
     */
    private static function namesRelativeCallable(Node $node): bool
    {
        $class = $node instanceof Array_ && count($node->items) === 2 ? $node->items[0]->value : null;

        return ($node instanceof String_ && preg_match('/^(self|static|parent)::/i', $node->value) === 1)
            || ($class instanceof String_ && in_array(strtolower($class->value), ['self', 'static', 'parent'], true));
    }

    /**
     * Whether a value may be a closure, which a helper calls: anything but a literal, an array or a named class's
     * new instance.
     */
    private static function mayBeClosure(Expr $value): bool
    {
        return ! ($value instanceof Scalar || $value instanceof ConstFetch || $value instanceof Array_ || self::isClassName($value)
            || ($value instanceof New_ && $value->class instanceof Name));
    }

    /**
     * Whether an expression is a `::class` name.
     */
    private static function isClassName(?Expr $expr): bool
    {
        return $expr instanceof ClassConstFetch && $expr->name instanceof Identifier && $expr->name->toLowerString() === 'class';
    }

    /**
     * The string a literal or a class constant spells, or null where the source spells none.
     */
    private static function literalString(?Expr $expr): ?string
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if (! $expr instanceof ClassConstFetch || ! $expr->class instanceof Name || $expr->class->isSpecialClassName()
            || ! $expr->name instanceof Identifier || self::isClassName($expr)
        ) {
            return null;
        }

        $constant = $expr->class->toString().'::'.$expr->name->toString();
        $value = defined($constant) ? constant($constant) : null;

        return is_string($value) ? $value : null;
    }

    /**
     * Whether a write through `$this` leaves a chain holding what its proof tested: one to another chain, or one
     * through the chain to a member or key, or a relation's member, that keepsModelsAt() or the untyped rule keeps.
     *
     * @param  NamedWrite  $write
     * @param  list<string>  $path
     * @param  non-empty-list<class-string>  $classes
     */
    private function keepsWrite(array $write, array $path, array $classes): bool
    {
        [, $node, $target] = $write;
        $written = $this->chainOf($target);

        if ($written === null) {
            return false;
        }

        $through = self::through($written, $path);

        if ($through === null) {
            return ! self::mayShareObject($written, $path);
        }

        // A key written on the chain itself is a member of the value it holds, as `$model['title']` is.
        if ($through === [] && ! ($target instanceof ArrayDimFetch && $written === $path)) {
            return false;
        }

        [$holder, $member] = ModelFills::writtenMember($node) ?? [null, ''];
        $held = $holder === null ? null : $this->chainOf($holder);

        return match (true) {
            $held === $path => ModelFills::namesNoTypedMember($classes, $member)
                || ModelFills::keepsModelsAt($node, $classes, null, $this->scope),
            $held !== null && count($held) === count($path) + 1 && self::through($held, $path) === [$held[count($path)]] => ModelFills::keepsModelsAt($node, $classes, $held[count($path)], $this->scope),
            default => false,
        };
    }

    /**
     * Whether an `unset()` may change what a chain holds: of the chain or one it reads through, of a typed member of
     * the value it holds, or of anything through a local; unsetting a local itself only unbinds it.
     *
     * @param  list<string>  $path
     * @param  non-empty-list<class-string>  $classes
     */
    private function unsetChanges(Unset_ $unset, array $path, array $classes): bool
    {
        return array_any($unset->vars, function (Expr $var) use ($path, $classes): bool {
            $root = self::rootOf($var);

            if (! $root instanceof Variable || $root->name !== 'this') {
                return $root instanceof Variable && $root !== $var;
            }

            $chain = $this->chainOf($var);
            $through = $chain === null ? [] : self::through($chain, $path);

            if ($through === null && self::mayShareObject($chain, $path)) {
                return true;
            }

            $member = match (true) {
                $through !== null && count($through) === 1 => $through[0],
                $through === [] && $chain === $path && $var instanceof ArrayDimFetch && $var->dim instanceof String_ => $var->dim->value,
                default => null,
            };

            return $through !== null && ($member === null || ! ModelFills::namesNoTypedMember($classes, $member));
        });
    }

    /**
     * Whether a call is known not to reach the chain: a JsonResource helper on `$this` given safe arguments, an ability
     * check on the signed-in user, a Gate facade check, Laravel's `route()` or `url()`, or a call whose receiver and
     * arguments safeValue() accepts.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters  the parameters of the closures around the call
     */
    private function safeCall(CallLike $call, array $path, array $parameters = []): bool
    {
        // A first-class callable binds its receiver for a later call, so that receiver must be safe to hand on.
        if ($call->isFirstClassCallable()) {
            $receiver = match (true) {
                $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $call->var,
                $call instanceof StaticCall => $call->class instanceof Name ? null : $call->class,
                $call instanceof FuncCall => $call->name instanceof Name ? null : $call->name,
                default => null,
            };

            return ! ($call instanceof StaticCall && $call->class instanceof Name && $call->class->isSpecialClassName())
                && ($receiver === null || $this->safeValue($receiver, $path, $parameters));
        }

        return match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $this->safeMethodCall($call, $path, $parameters),
            $call instanceof StaticCall => $this->safeStaticCall($call, $path, $parameters),
            $call instanceof FuncCall => $this->safeFunctionCall($call, $path, $parameters),
            $call instanceof New_ => $call->class instanceof Name && ! $call->class->isSpecialClassName()
                && $this->safeArguments($call, $path, $parameters),
            default => false,
        };
    }

    /**
     * Whether a method call is known not to reach the chain.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters  the parameters of the closures around the call
     */
    private function safeMethodCall(MethodCall|NullsafeMethodCall $call, array $path, array $parameters): bool
    {
        $name = $call->name instanceof Identifier ? $call->name->toLowerString() : null;

        return match (true) {
            $name === null => false,
            $call->var instanceof Variable && $call->var->name === 'this' => $this->safeHelperCall($call, $path, $parameters),
            in_array($name, self::ABILITY_CHECKS, true) && $this->signedInUser($call->var, $call) => true,
            // Reading the signed-in user an ability check runs on reaches nothing else.
            $name === 'user' && $this->signedInUser($call, $call) => true,
            default => $this->safeValue($call->var, $path, $parameters) && $this->safeArguments($call, $path, $parameters),
        };
    }

    /**
     * Whether a call on `$this` runs a JsonResource helper that only reads, given safe arguments. A callback it may
     * call is a closure written there, never another callable; the first parameter of the one it hands a member of the
     * resource stands for that member, and anything else there that may hold a closure may do anything with the member.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters  the parameters of the closures around the call
     */
    private function safeHelperCall(MethodCall|NullsafeMethodCall $call, array $path, array $parameters): bool
    {
        $helper = $call->name instanceof Identifier && in_array($call->name->toLowerString(), self::RESOURCE_HELPERS, true)
            ? $this->readingHelper($call)
            : null;

        if ($helper === null) {
            return false;
        }

        $arguments = CallArguments::for($call, $helper);
        $callbacks = array_map(fn (string $name): ?Expr => $arguments->named($name)?->value, self::CALLBACK_PARAMETERS);
        [$handedTo, $member] = $this->handedMember($call, $helper) ?? [null, null];

        return ! $arguments->hasUnpack() && array_all($call->getArgs(), fn (Node\Arg $arg): bool => match (true) {
            in_array($arg->value, $callbacks, true) && self::namesCallable($arg->value) => false,
            $arg->value !== $handedTo || $member === null => $this->safeValue($arg->value, $path, $parameters),
            $arg->value instanceof ClosureExpr || $arg->value instanceof ArrowFunction => $this->safeClosure($arg->value, $path, $parameters, $member),
            default => (($member !== false && ! self::strictPrefix($member, $path)) || ! self::mayBeClosure($arg->value))
                && $this->safeValue($arg->value, $path, $parameters),
        });
    }

    /**
     * The callback a helper on `$this` calls with a member of the resource, and that member as the chain it reads,
     * false where the source spells no name for it; null for a helper that hands its callbacks nothing.
     *
     * @return array{Expr, list<string>|false}|null
     */
    private function handedMember(MethodCall|NullsafeMethodCall $call, ReflectionMethod $helper): ?array
    {
        $name = strtolower($helper->getName());
        $arguments = CallArguments::for($call, $helper);
        $callback = $arguments->named('value')?->value;

        if ($callback === null || ! in_array($name, self::HANDING_HELPERS, true)) {
            return null;
        }

        $relation = self::literalString($arguments->at(0)?->value);
        [$column, $aggregate] = $name === 'whenaggregated'
            ? [self::literalString($arguments->named('column')?->value), self::literalString($arguments->named('aggregate')?->value)]
            : ['', ''];

        if ($relation === null || $column === null || $aggregate === null || $arguments->hasUnpack()) {
            return [$callback, false];
        }

        // The attribute each helper reads, as ConditionallyLoadsAttributes builds it.
        $member = match ($name) {
            'whencounted' => Str::of($relation)->snake()->finish('_count'),
            'whenaggregated' => Str::of($relation)->snake()->append('_')->append($aggregate)->append('_')->finish($column),
            'whenexistsloaded' => Str::of($relation)->snake()->finish('_exists'),
            default => Str::of($relation),
        };

        return [$callback, ['resource', $member->value()]];
    }

    /**
     * Whether a static call is known not to reach the chain; one through `self`, `static`, `parent` or an expression
     * may run on `$this`.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters  the parameters of the closures around the call
     */
    private function safeStaticCall(StaticCall $call, array $path, array $parameters): bool
    {
        $class = $call->class;

        return match (true) {
            ! $class instanceof Name || $class->isSpecialClassName() => false,
            $this->bindsThis($class->toString(), $call->name instanceof Identifier ? $call->name->toString() : null) => false,
            in_array($class->toString(), [Gate::class, 'Gate'], true) && $call->name instanceof Identifier
                && in_array($call->name->toLowerString(), self::GATE_CHECKS, true) => true,
            default => $this->safeArguments($call, $path, $parameters),
        };
    }

    /**
     * Whether a function call is known not to reach the chain; invoking any callable value but a closure written
     * there, or handing one to call_user_func(), may run anything.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters  the parameters of the closures around the call
     */
    private function safeFunctionCall(FuncCall $call, array $path, array $parameters): bool
    {
        $name = $call->name;

        if (! $name instanceof Name) {
            return ($name instanceof ClosureExpr || $name instanceof ArrowFunction) && $this->safeValue($name, $path, $parameters)
                && $this->safeArguments($call, $path, $parameters);
        }

        if (self::writesScope($call)) {
            return false;
        }

        if (self::runsLaravelHelper($name, self::URL_HELPERS)) {
            return true;
        }

        $callable = in_array($name->toLowerString(), self::CALLERS, true) ? $call->getArgs()[0]->value ?? null : null;
        $named = $callable instanceof String_ && str_contains($callable->value, '::') ? explode('::', $callable->value, 2) : null;

        return ($callable === null || $callable instanceof String_ || $callable instanceof ClosureExpr || $callable instanceof ArrowFunction)
            && ($named === null || ! $this->bindsThis($named[0], $named[1]))
            && $this->safeArguments($call, $path, $parameters);
    }

    /**
     * Whether every argument of a call is safe to hand on, and none passes the chain, or one through it, by reference.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters  the parameters of the closures around the call
     */
    private function safeArguments(CallLike $call, array $path, array $parameters): bool
    {
        return array_all($call->getArgs(), fn (Node\Arg $arg): bool => $this->safeValue($arg->value, $path, $parameters)
            && ! ($this->chainOf($arg->value) !== null && $this->passesByReference($call, $arg->value)));
    }

    /**
     * Whether an expression is safe to hand to a call: it holds no local, no `$this` but as the root of the chain, a
     * chain through it or one apart from it, no static name of the subject, and no closure safeClosure() refuses. A
     * closure's parameter holds nothing of the subject, unless it stands for a member a helper hands it.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters
     */
    private function safeValue(Node $node, array $path, array $parameters = [], bool $acrossCalls = true): bool
    {
        if (! $acrossCalls && $node instanceof CallLike) {
            return true;
        }

        if ($node instanceof Variable && is_string($node->name) && $node->name !== 'this' && array_key_exists($node->name, $parameters)) {
            return $parameters[$node->name] === null || ! self::strictPrefix($parameters[$node->name], $path);
        }

        $root = $node instanceof Expr ? self::rootOf($node) : null;

        if ($root instanceof Variable && ($root->name === 'this' || (is_string($root->name) && isset($parameters[$root->name])))) {
            $chain = $this->chainOf($node, $parameters);

            return $chain !== null && ! self::strictPrefix($chain, $path) && $this->safeChildren($node, $path, $parameters, $acrossCalls, $root);
        }

        if ($node instanceof Variable) {
            return false;
        }

        return match (true) {
            $node instanceof ClosureExpr, $node instanceof ArrowFunction => $this->safeClosure($node, $path, $parameters),
            $node instanceof ClassLike, self::namesRelativeCallable($node) => false,
            ($node instanceof ClassConstFetch || $node instanceof StaticPropertyFetch || $node instanceof StaticCall
                || $node instanceof New_) && ! ($node->class instanceof Name && ! $node->class->isSpecialClassName()) => false,
            default => $this->safeChildren($node, $path, $parameters, $acrossCalls),
        };
    }

    /**
     * Whether every expression a node holds is safe to hand on, leaving out $skip, the `$this` a chain is read off.
     *
     * @param  list<string>  $path
     * @param  Parameters  $parameters
     */
    private function safeChildren(Node $node, array $path, array $parameters, bool $acrossCalls, ?Node $skip = null): bool
    {
        foreach ($node->getSubNodeNames() as $name) {
            foreach (is_array($node->{$name}) ? $node->{$name} : [$node->{$name}] as $child) {
                if ($child instanceof Node && $child !== $skip
                    && ! ($this->isChainLink($node, $child)
                        ? $this->safeChildren($child, $path, $parameters, $acrossCalls, $skip)
                        : $this->safeValue($child, $path, $parameters, $acrossCalls))
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Whether a child is the receiver a chain of property and key reads is read off, inside a chain already judged.
     */
    private function isChainLink(Node $node, Node $child): bool
    {
        return ($node instanceof PropertyFetch || $node instanceof NullsafePropertyFetch || $node instanceof ArrayDimFetch)
            && $child === $node->var && ($child instanceof PropertyFetch || $child instanceof NullsafePropertyFetch || $child instanceof ArrayDimFetch);
    }

    /**
     * Whether a closure is safe to hand to a call: it imports by value and never writes an import, writes through
     * nothing, and holds no value safeValue() refuses outside its calls, read with the parameters closureParameters()
     * gives it. Its calls are read with the rest of the span, which reaches into closures.
     *
     * @param  list<string>  $path
     * @param  Parameters  $outer
     * @param  list<string>|false|null  $handed  the member a helper hands the closure, as the chain it reads
     */
    private function safeClosure(ClosureExpr|ArrowFunction $closure, array $path, array $outer, array|false|null $handed = null): bool
    {
        $parameters = self::closureParameters($closure, $outer, $handed);
        $body = $closure instanceof ArrowFunction ? [$closure->expr] : $closure->stmts;
        $imports = $closure instanceof ClosureExpr ? array_map(fn (Node\ClosureUse $use): string|Expr => $use->var->name, $closure->uses) : [];

        return ! ($closure instanceof ClosureExpr && array_any($closure->uses, fn (Node\ClosureUse $use): bool => $use->byRef))
            && ! array_any(VariableWrites::of($body), fn (array $write): bool => in_array($write[0], $imports, true) || self::writesThrough($write[2]))
            && array_all($body, fn (Node $node): bool => $this->safeValue($node, $path, $parameters, false));
    }

    /**
     * Whether a receiver is the signed-in user: `auth()->user()`, or `user()` on a parameter the method holding the
     * call types a request, which neither the method nor a closure around the call rebinds.
     */
    private function signedInUser(Expr $receiver, Node $call): bool
    {
        if (! ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) || ! $receiver->name instanceof Identifier
            || $receiver->name->toLowerString() !== 'user'
        ) {
            return false;
        }

        $holder = $receiver->var;

        return match (true) {
            $holder instanceof FuncCall => $holder->name instanceof Name && self::runsLaravelHelper($holder->name, ['auth']),
            $holder instanceof Variable && is_string($holder->name) => $this->holdsRequestParameter($holder->name, $call),
            default => false,
        };
    }

    /**
     * Whether a variable read by a call is the request parameter of the method holding the call: the signature types it
     * a request, the body never writes it or a variable no scan names, and no closure around the call takes a parameter
     * of that name or imports it by reference, writes it, or fails to import it.
     */
    private function holdsRequestParameter(string $name, Node $call): bool
    {
        $method = $this->methodHolding($call);
        $parameter = $method === null ? null : array_find($method->getParameters(), fn ($parameter): bool => $parameter->getName() === $name);
        $type = $parameter?->getType();
        $body = $method === null ? null : resolve(MethodLocator::class)->locate($method->getDeclaringClass()->getName(), $method->getName())?->method;

        if (! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Request::class, true) || $body === null
            || $body->getStartFilePos() > $call->getStartFilePos() || $body->getEndFilePos() < $call->getEndFilePos()
            || array_any(VariableWrites::of($body->stmts ?? []), fn (array $write): bool => $write[0] === $name)
            || self::writesUnseen($body->stmts ?? [])
        ) {
            return false;
        }

        /** @var list<ArrowFunction|ClosureExpr> $around */
        $around = new NodeFinder()->find($body->stmts ?? [], fn (Node $node): bool => ($node instanceof ClosureExpr || $node instanceof ArrowFunction)
            && $node->getStartFilePos() <= $call->getStartFilePos() && $node->getEndFilePos() >= $call->getEndFilePos());

        return array_all($around, fn (ArrowFunction|ClosureExpr $function): bool => ! array_any(
            $function->params,
            fn (Param $param): bool => $param->var instanceof Variable && $param->var->name === $name,
        ) && ($function instanceof ArrowFunction || (array_any($function->uses, fn (Node\ClosureUse $use): bool => $use->var->name === $name && ! $use->byRef)
            && ! array_any(VariableWrites::of($function->stmts), fn (array $write): bool => $write[0] === $name))));
    }

    /**
     * The method of the declaring class whose body holds a node, read off the lines its declaring file gives.
     */
    private function methodHolding(Node $node): ?ReflectionMethod
    {
        $line = $node->getStartLine();

        return array_find(
            $this->scope->declaringClass->getMethods(),
            fn (ReflectionMethod $method): bool => $method->getStartLine() <= $line && $method->getEndLine() >= $line
                && DeclaringFile::ofMethod($method)->getFileName() === $this->scope->declaringFileClass->getFileName(),
        );
    }

    /**
     * The calls, `unset()` statements and code runsUnseen() flags in the body, closures and arrow functions included,
     * since they may run before a read, each call with the parameters of the closures around it; never a nested class's
     * or named function's. Scanned once, on the first proof that asks.
     *
     * @return array{list<array{CallLike, Parameters}>, list<Unset_>, list<Node>}
     */
    private function nodes(): array
    {
        if ($this->nodes !== null) {
            return $this->nodes;
        }

        $calls = [];
        $unsets = [];
        $unseen = [];
        $handed = [];
        /** @var list<array{mixed, Parameters}> $stack */
        $stack = array_map(fn (Node $stmt): array => [$stmt, []], $this->stmts);

        while ($stack !== []) {
            [$node, $parameters] = array_pop($stack);

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_) {
                continue;
            }

            if ($node instanceof CallLike) {
                $calls[] = [$node, $parameters];
                $handed += $this->handedClosure($node);
            } elseif ($node instanceof Unset_) {
                $unsets[] = $node;
            } elseif (self::runsUnseen($node)) {
                $unseen[] = $node;
            }

            $inner = $node instanceof ClosureExpr || $node instanceof ArrowFunction
                ? self::closureParameters($node, $parameters, $handed[spl_object_id($node)] ?? null)
                : $parameters;

            foreach ($node->getSubNodeNames() as $name) {
                foreach (is_array($node->{$name}) ? $node->{$name} : [$node->{$name}] as $child) {
                    $stack[] = [$child, $inner];
                }
            }
        }

        return $this->nodes = [$calls, $unsets, $unseen];
    }

    /**
     * The member a helper call on `$this` hands the closure written as its callback, keyed by that closure's object id.
     *
     * @return array<int, list<string>|false>
     */
    private function handedClosure(CallLike $call): array
    {
        $helper = ($call instanceof MethodCall || $call instanceof NullsafeMethodCall) && $call->var instanceof Variable
            && $call->var->name === 'this' && $call->name instanceof Identifier
            && in_array($call->name->toLowerString(), self::HANDING_HELPERS, true) ? $this->readingHelper($call) : null;
        [$callback, $member] = ($helper === null ? null : $this->handedMember($call, $helper)) ?? [null, false];

        return $callback instanceof ClosureExpr || $callback instanceof ArrowFunction ? [spl_object_id($callback) => $member] : [];
    }

    /**
     * Whether the body binds a local by reference anywhere, so that a write to it may write what it is bound to; any
     * local may be where extract() or a variable variable binds one by a name no scan reads.
     */
    private function bindsByReference(string $name): bool
    {
        $this->references ??= self::writesUnseen($this->stmts) ? ['' => true] : array_fill_keys(array_column(array_filter(
            $this->writes,
            fn (array $write): bool => $write[3]->makesReference() || self::destructuresByReference($write[2], $write[0]),
        ), 0), true);

        return isset($this->references['']) || isset($this->references[$name]);
    }

    /**
     * Whether a call through a class name runs on `$this`, as PHP binds it for a non-static method of a class the
     * object is; a dynamic name may be any method.
     */
    private function bindsThis(string $class, ?string $method): bool
    {
        $class = ltrim($class, '\\');
        $own = is_a($this->scope->runtime->class->getName(), $class, true)
            || is_a($this->scope->declaringClass->getName(), $class, true);

        return $own && ($method === null || ! method_exists($class, $method) || ! new ReflectionMethod($class, $method)->isStatic());
    }

    /**
     * Whether a call takes an argument by reference, as ByReferenceArguments reads a callee the call names.
     */
    private function passesByReference(CallLike $call, Expr $value): bool
    {
        $passed = array_filter($call->getArgs(), fn (Node\Arg $arg): bool => $arg->value === $value);

        return resolve(ByReferenceArguments::class)->of($call, $passed, $this->scope->declaringClass, $this->scope->runtime->class) !== [];
    }

    /**
     * The helper a call on `$this` runs, where the framework's resource classes declare it and it only reads.
     */
    private function readingHelper(MethodCall|NullsafeMethodCall $call): ?ReflectionMethod
    {
        $name = $call->name instanceof Identifier ? $call->name->toString() : null;
        $class = $name === null ? null : $this->scope->thisMemberClass($name, true);
        $method = $name !== null && $class !== null && $class->hasMethod($name) ? $class->getMethod($name) : null;

        return $method !== null && in_array(DeclaringFile::ofMethod($method)->getName(), self::READING_DECLARERS, true) ? $method : null;
    }

    /**
     * Whether a node is in force between two offsets, outside every body in $exits.
     *
     * @param  list<array<Node\Stmt>>  $exits
     */
    private function lands(Node $node, int $from, ?int $to, array $exits): bool
    {
        return $node->getEndFilePos() >= $from
            && ($to === null || $node->getStartFilePos() <= $to)
            && ! array_any($exits, fn (array $body): bool => $body !== []
                && $node->getStartFilePos() >= $body[0]->getStartFilePos()
                && $node->getEndFilePos() <= $body[array_key_last($body)]->getEndFilePos());
    }

    /**
     * The chain on `$this` an expression writes or reads, keys stripped and in the spelling every alias shares, none
     * for `$this` itself, read on from the chain a closure parameter in $parameters stands for; null where a name, a
     * call or the root is not one the source spells.
     *
     * @param  Parameters  $parameters
     * @return list<string>|null
     */
    private function chainOf(Expr $expr, array $parameters = []): ?array
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        $names = [];

        while (($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) && $expr->name instanceof Identifier) {
            array_unshift($names, $expr->name->toString());
            $expr = $expr->var;
        }

        $root = match (true) {
            ! $expr instanceof Variable || ! is_string($expr->name) => null,
            $expr->name === 'this' => [],
            default => $parameters[$expr->name] ?? null,
        };

        return $root === null ? null : $this->canonical([...$root, ...$names]);
    }

    /**
     * A chain as an API resource reads it: one the subject does not declare goes through `$this->resource`.
     *
     * @param  list<string>  $path
     * @return list<string>
     */
    private function canonical(array $path): array
    {
        $first = $path[0] ?? 'resource';

        return $first !== 'resource' && $this->scope->hasResourceProxy() && ! $this->scope->thisMemberClass($first, false)->hasProperty($first)
            ? ['resource', ...$path]
            : $path;
    }
}
