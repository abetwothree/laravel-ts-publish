<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\CollectsResources;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Gate;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Unset_;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * What in a body may change what a chain of property reads on `$this` holds, so the guard pass binds no `instanceof`
 * proof of it across: an assignment to it or a chain it reads through, `unset()` of either, either passed by reference,
 * `$this` or a chain it reads through passed at all, a call on either, or a callable value called or passed.
 *
 * Only a write through the chain itself keeps the proof, and only an authorization check, `route()` or `url()` a pass.
 * On an API resource a chain the subject does not declare reads through `$this->resource`.
 *
 * @phpstan-type ThisEffect = array{'unset'|'call'|'pass'|'invoke', Node, Expr}
 *
 * @phpstan-import-type NamedWrite from VariableWrites
 * @phpstan-import-type NamedWrites from VariableWrites
 *
 * @internal
 */
final class ThisPathWrites
{
    /** The framework's resource classes and traits whose methods read the resource and write no chain on `$this`. */
    private const array READING_DECLARERS = [
        JsonResource::class, ResourceCollection::class, ConditionallyLoadsAttributes::class, CollectsResources::class,
    ];

    /** The methods that check an ability on any receiver, reading what they are given. */
    private const array ABILITY_CHECKS = ['can', 'cannot', 'canany'];

    /** The Gate facade's checks, which read what they are given. */
    private const array GATE_CHECKS = ['allows', 'denies', 'check', 'any', 'none'];

    /** The helpers that only read a model to build a URL. */
    private const array URL_HELPERS = ['route', 'url'];

    /** The built-ins that call the callable they are given first. */
    private const array CALLERS = ['call_user_func', 'call_user_func_array'];

    /** @var list<ThisEffect>|null */
    private ?array $effects = null;

    /**
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
            [$name, $node, , $kind] = $write;

            // A `foreach` around the test assigns its value before each pass, so never between the test and a read.
            if (($kind === VariableWrite::Foreach && $node->getStartFilePos() < $from) || ! $this->lands($node, $from, $to, $exits)) {
                continue;
            }

            // A write through a local standing for a chain is one through that chain.
            $through = $name === 'this' || ($kind === VariableWrite::MemberOrOffset && $this->standsFor($write[2]) !== null);

            if ($through && ! $this->keepsWrite($write, $path, $classes)) {
                return true;
            }
        }

        return array_any(
            $this->effects(),
            fn (array $effect): bool => $this->lands($effect[1], $from, $to, $exits) && $this->changes($effect, $path, $classes),
        );
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
     * The chain an expression of no call spells on `$this` or on a local standing for one, its names unread; null for
     * any other root.
     *
     * @return list<string>|null
     */
    private static function rawChain(Expr $expr): ?array
    {
        return $expr instanceof Variable && $expr->name === 'this' ? [] : AnalysisScope::thisReadPath($expr);
    }

    /**
     * Whether a call runs Laravel's own `route()` or `url()` helper: no namespaced function of that name shadows it,
     * and the global one is the framework's.
     */
    private static function runsUrlHelper(Name $name): bool
    {
        $namespaced = $name->getAttribute('namespacedName');

        if (! in_array($name->toLowerString(), self::URL_HELPERS, true)
            || ($namespaced instanceof Name && function_exists($namespaced->toString()))
            || ! function_exists($name->toString())
        ) {
            return false;
        }

        $file = str_replace('\\', '/', (string) new ReflectionFunction($name->toString())->getFileName());

        return str_ends_with($file, '/Illuminate/Foundation/helpers.php');
    }

    /**
     * The shortest chain on `$this` an array literal holds, at any depth.
     *
     * @return list<string>|null
     */
    private static function heldChain(Array_ $array): ?array
    {
        $found = null;

        foreach ($array->items as $item) {
            $chain = $item->value instanceof Array_ ? self::heldChain($item->value) : self::rawChain($item->value);
            $found = $chain !== null && ($found === null || count($chain) < count($found)) ? $chain : $found;
        }

        return $found;
    }

    /**
     * The root of a chain of property and key reads, and of method calls too with $throughCalls.
     */
    private static function rootOf(Expr $expr, bool $throughCalls): Expr
    {
        while ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch || $expr instanceof ArrayDimFetch
            || ($throughCalls && ($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall))
        ) {
            $expr = $expr->var;
        }

        return $expr;
    }

    /**
     * The values the body assigns to a local, by `=`, `??=` or `=&`.
     *
     * @return list<Expr>
     */
    private function assigned(string $name): array
    {
        $values = [];

        foreach ($this->writes as [$written, $node]) {
            if ($written === $name && ($node instanceof Assign || $node instanceof AssignRef || $node instanceof CoalesceAssign)) {
                $values[] = $node->expr;
            }
        }

        return $values;
    }

    /**
     * The values an argument passes that a chain on `$this` may be reached through: one rooted at `$this` or at a
     * local standing for a chain, or a local holding an array of them, itself or each an array literal holds.
     *
     * @return list<Expr>
     */
    private function chainValues(Expr $value): array
    {
        if ($value instanceof Array_) {
            return array_merge(...array_map(
                fn (?ArrayItem $item): array => $item === null ? [] : $this->chainValues($item->value),
                $value->items,
            ));
        }

        $root = self::rootOf($value, true);
        $alias = $root instanceof Variable ? $this->aliasOf($root) : null;

        return ($root instanceof Variable && $root->name === 'this') || ($alias !== null && (! $alias[1] || $root === $value))
            ? [$value]
            : [];
    }

    /**
     * Whether an expression is `$this` or a chain of property or key reads on it, or on a local standing for a chain.
     */
    private function readsChain(Expr $expr): bool
    {
        $root = self::rootOf($expr, false);

        return $root instanceof Variable && ($root->name === 'this' || $this->standsFor($root) !== null);
    }

    /**
     * The chain a write target's or a read's local root stands for, unless an array literal holds it; null for `$this`
     * and any other root.
     *
     * @return list<string>|null
     */
    private function standsFor(Expr $expr): ?array
    {
        $root = self::rootOf($expr, false);
        $alias = $root instanceof Variable ? $this->aliasOf($root) : null;

        return $alias === null || $alias[1] ? null : $alias[0];
    }

    /**
     * The chain a local stands for where a write reaching it holds `$this` or a chain of property reads on it, and
     * whether that write is an array literal holding one; the shortest where several do.
     *
     * @return array{list<string>, bool}|null
     */
    private function aliasOf(Variable $local): ?array
    {
        if (! is_string($local->name) || $local->name === 'this') {
            return null;
        }

        $found = null;

        // Where the scan cannot tell which write reaches, as in a block writing through the local, any assignment can.
        foreach ($this->scope->writesSeenBy($local) ?? $this->assigned($local->name) as $write) {
            $chain = $write instanceof Array_ ? self::heldChain($write) : self::rawChain($write);

            if ($chain !== null && ($found === null || count($chain) < count($found[0]))) {
                $found = [$chain, $write instanceof Array_];
            }
        }

        return $found;
    }

    /**
     * Whether a receiver is `user()` on a parameter the signature of the method holding it types a request, which an
     * API resource's scope leaves unbound.
     */
    private function readsRequestUser(Expr $receiver): bool
    {
        $variable = ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) && $receiver->name instanceof Identifier
            && $receiver->name->toLowerString() === 'user' ? $receiver->var : null;
        $name = $variable instanceof Variable && is_string($variable->name) ? $variable->name : null;
        $line = $receiver->getStartLine();

        foreach ($name === null ? [] : $this->scope->declaringClass->getMethods() as $method) {
            if ($method->getStartLine() > $line || $method->getEndLine() < $line
                || DeclaringFile::ofMethod($method)->getFileName() !== $this->scope->declaringFileClass->getFileName()
            ) {
                continue;
            }

            return array_any($method->getParameters(), fn (ReflectionParameter $parameter): bool => $parameter->getName() === $name
                && $parameter->getType() instanceof ReflectionNamedType && is_a($parameter->getType()->getName(), Request::class, true));
        }

        return false;
    }

    /**
     * Whether a call only reads what it is given: an ability check on an `Authorizable`, a Gate facade check, or
     * Laravel's `route()` or `url()`.
     */
    private function readsOnly(Node $call): bool
    {
        return match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $call->name instanceof Identifier
                && in_array($call->name->toLowerString(), self::ABILITY_CHECKS, true) && $this->authorizes($call->var),
            $call instanceof StaticCall => $call->class instanceof Name && $call->name instanceof Identifier
                && in_array($call->class->toString(), [Gate::class, 'Gate'], true)
                && in_array($call->name->toLowerString(), self::GATE_CHECKS, true),
            $call instanceof FuncCall => $call->name instanceof Name && self::runsUrlHelper($call->name),
            default => false,
        };
    }

    /**
     * Whether every class a receiver holds is an `Authorizable`, whose ability checks run the app's gates.
     */
    private function authorizes(Expr $receiver): bool
    {
        $classes = resolve(ReceiverClassResolver::class)->resolve($receiver, $this->scope)->classes ?? [];
        $model = $classes === [] && $this->readsRequestUser($receiver) ? resolve(AuthUserResolver::class)->model() : null;
        $classes = $model === null ? $classes : [$model];

        return $classes !== [] && array_all($classes, fn (string $class): bool => is_a($class, Authorizable::class, true));
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
            return true;
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
     * Whether an `unset()`, a call or a by-reference argument may change what a chain holds.
     *
     * @param  ThisEffect  $effect
     * @param  list<string>  $path
     * @param  non-empty-list<class-string>  $classes
     */
    private function changes(array $effect, array $path, array $classes): bool
    {
        [$kind, $node, $expr] = $effect;
        $chain = $this->chainOf($expr);
        $through = $chain === null ? [] : self::through($chain, $path);

        return match ($kind) {
            // Unsetting a member of the value the chain holds nulls only what no column or relation types.
            'unset' => $through !== null && ! (count($through) === 1 && ModelFills::namesNoTypedMember($classes, $through[0]))
                && ! ($through === [] && $chain === $path && $expr instanceof ArrayDimFetch && $expr->dim instanceof String_
                    && ModelFills::namesNoTypedMember($classes, $expr->dim->value)),
            'pass' => $this->passChanges($node, $expr, $chain, $through, $path),
            // A closure made before the test, or any callable value, may replace the chain when it runs.
            'invoke' => true,
            // A call on a chain the proof's reads through may replace it; one on the chain or past it leaves it.
            default => $chain === [] ? ! $this->onlyReads($node) : $through === [] && $chain !== $path,
        };
    }

    /**
     * The `unset()` targets, calls on `$this` or a chain of it, calls of a callable value, and values rooted at `$this`
     * a call is given, in the body, closures and arrow functions included, since they may run before a read; scanned
     * once, on the first proof that asks.
     *
     * @return list<ThisEffect>
     */
    private function effects(): array
    {
        if ($this->effects !== null) {
            return $this->effects;
        }

        $effects = [];
        $nodes = $this->stmts;

        while ($nodes !== []) {
            $node = array_pop($nodes);

            if (! $node instanceof Node || $node instanceof ClassLike || $node instanceof Function_) {
                continue;
            }

            foreach ($this->effectsOf($node) as $effect) {
                $effects[] = $effect;
            }

            foreach ($node->getSubNodeNames() as $name) {
                foreach (is_array($node->{$name}) ? $node->{$name} : [$node->{$name}] as $child) {
                    $nodes[] = $child;
                }
            }
        }

        return $this->effects = $effects;
    }

    /**
     * What one node may do to a chain on `$this`, each as an effect over the expression it acts on.
     *
     * @return list<ThisEffect>
     */
    private function effectsOf(Node $node): array
    {
        // Unsetting a local itself only unbinds it, so only what goes through one standing for a chain counts.
        if ($node instanceof Unset_) {
            return array_values(array_map(
                fn (Expr $var): array => ['unset', $node, $var],
                array_filter($node->vars, fn (Expr $var): bool => $this->readsChain($var) && ! ($var instanceof Variable && $var->name !== 'this')),
            ));
        }

        if ($node instanceof FuncCall && ! $node->name instanceof Name) {
            return [['invoke', $node, $node->name]];
        }

        if (! $node instanceof CallLike) {
            return [];
        }

        $receiver = match (true) {
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $node->var,
            $node instanceof StaticCall && $node->class instanceof Name => $node->class->isSpecialClassName() ? new Variable('this') : null,
            $node instanceof StaticCall => $node->class,
            default => null,
        };
        // A call on what another call returns has its own effect at that inner call.
        $onChain = $receiver !== null && $this->readsChain($receiver);

        // A first-class callable binds its receiver for a later call, as a pass of it does.
        if ($node->isFirstClassCallable()) {
            return $onChain ? [['pass', $node, $receiver]] : [];
        }

        $effects = $onChain ? [['call', $node, $receiver]] : [];
        $callable = $node instanceof FuncCall && in_array($node->name->toLowerString(), self::CALLERS, true)
            ? $node->getArgs()[0]->value ?? null
            : null;

        if ($callable !== null && ! ($callable instanceof String_ || $callable instanceof Array_
            || $callable instanceof ArrowFunction || $callable instanceof ClosureExpr)
        ) {
            $effects[] = ['invoke', $node, $callable];
        }

        foreach ($node->getArgs() as $arg) {
            foreach ($this->chainValues($arg->value) as $value) {
                $effects[] = ['pass', $node, $value];
            }

            // A callable a local holds may run in the call it is passed to, as one called directly does.
            foreach ($this->callableLocals($arg->value) as $local) {
                $effects[] = ['invoke', $node, $local];
            }
        }

        return $effects;
    }

    /**
     * The locals an argument passes, itself or inside an array literal, whose reaching write is a closure, an arrow
     * function or a first-class callable.
     *
     * @return list<Variable>
     */
    private function callableLocals(Expr $value): array
    {
        if ($value instanceof Array_) {
            return array_merge(...array_map(
                fn (?ArrayItem $item): array => $item === null ? [] : $this->callableLocals($item->value),
                $value->items,
            ));
        }

        if (! $value instanceof Variable || $value->name === 'this') {
            return [];
        }

        return array_any($this->scope->writesSeenBy($value) ?? [], fn (Expr $write): bool => $write instanceof ClosureExpr
            || $write instanceof ArrowFunction || ($write instanceof CallLike && $write->isFirstClassCallable())) ? [$value] : [];
    }

    /**
     * Whether a value a call is given may change a chain: `$this`, or a chain the proof's reads through, or a local
     * standing for one, given to anything but a check that only reads it; the chain itself, or one through it, by
     * reference.
     *
     * @param  list<string>|null  $chain
     * @param  list<string>|null  $through
     * @param  list<string>  $path
     */
    private function passChanges(Node $call, Expr $value, ?array $chain, ?array $through, array $path): bool
    {
        // A call may keep what it is given and replace the chain through it, as `$this->resource->setRelation()` can.
        $holds = ($chain === null && $this->readsChain($value)) || ($through === [] && $chain !== $path);

        return match (true) {
            $holds => ! $this->readsOnly($call),
            $through === null => false,
            default => $call instanceof CallLike && ! $call->isFirstClassCallable() && $this->passesByReference($call, $value),
        };
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
     * Whether a call on `$this`, `self`, `static` or `parent` runs a method the framework's resource classes declare,
     * which reads the resource and writes no chain.
     */
    private function onlyReads(Node $call): bool
    {
        $name = ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)
            && $call->name instanceof Identifier ? $call->name->toString() : null;
        $class = match (true) {
            $name === null => null,
            $call instanceof StaticCall && $call->class instanceof Name => $this->scope->relativeClass($call->class),
            default => $this->scope->thisMemberClass($name, true),
        };

        return $name !== null && $class !== null && $class->hasMethod($name)
            && in_array(DeclaringFile::ofMethod($class->getMethod($name))->getName(), self::READING_DECLARERS, true);
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
     * The chain on `$this` an expression writes or reads, keys stripped and in the spelling every alias shares, read
     * through a local standing for a chain, or holding an array of them where it is the whole expression; null where a
     * name, a call or the root is not one the source spells.
     *
     * @return list<string>|null
     */
    private function chainOf(Expr $expr): ?array
    {
        $bare = $expr;

        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        $names = [];

        while ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) {
            if (! $expr->name instanceof Identifier) {
                return null;
            }

            array_unshift($names, $expr->name->toString());
            $expr = $expr->var;
        }

        $alias = $expr instanceof Variable ? $this->aliasOf($expr) : null;

        return match (true) {
            $expr instanceof Variable && $expr->name === 'this' => $this->canonical($names),
            $alias === null || ($alias[1] && $expr !== $bare) => null,
            default => $this->canonical([...$alias[0], ...$names]),
        };
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
