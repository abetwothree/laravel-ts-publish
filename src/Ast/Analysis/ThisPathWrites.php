<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Calls\ByReferenceArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DeclaringFile;
use Illuminate\Http\Resources\CollectsResources;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\CallLike;
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

/**
 * What in a body may change what a chain of property reads on `$this` holds, so the guard pass binds no `instanceof`
 * proof of it across: an assignment to it or a chain it reads through, `unset()` of either, either passed by reference,
 * or a call on `$this` or a chain it reads through. Only a write through the chain itself can keep the proof.
 *
 * On an API resource a chain the subject does not declare reads through `$this->resource`.
 *
 * @phpstan-type ThisEffect = array{'unset'|'call'|'pass', Node, Expr}
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
            if ($name === 'this' && ! ($kind === VariableWrite::Foreach && $node->getStartFilePos() < $from)
                && $this->lands($node, $from, $to, $exits) && ! $this->keepsWrite($write, $path, $classes)
            ) {
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
     * Whether an expression is `$this` or a chain of property or key reads on it, whatever names it spells.
     */
    private static function readsChain(Expr $expr): bool
    {
        while ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch || $expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === 'this';
    }

    /**
     * Whether an expression reads through `$this`: a chain of property, key or method reads rooted at it.
     */
    private static function rootsAtThis(Expr $expr): bool
    {
        while ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch || $expr instanceof ArrayDimFetch
            || $expr instanceof MethodCall || $expr instanceof NullsafeMethodCall
        ) {
            $expr = $expr->var;
        }

        return $expr instanceof Variable && $expr->name === 'this';
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
            'pass' => $through !== null && $node instanceof CallLike && $this->passesByReference($node, $expr),
            // A call on a chain the proof's reads through may replace it; one on the chain or past it leaves it.
            default => $expr instanceof Variable ? ! $this->onlyReads($node) : $through === [] && $chain !== $path,
        };
    }

    /**
     * The `unset()` targets, calls on `$this` or a chain of it, and arguments rooted at `$this` in the body, closures
     * and arrow functions included, since they may run before a read; scanned once, on the first proof that asks.
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
        if ($node instanceof Unset_) {
            return array_values(array_map(
                fn (Expr $var): array => ['unset', $node, $var],
                array_filter($node->vars, self::rootsAtThis(...)),
            ));
        }

        if (! $node instanceof CallLike || $node->isFirstClassCallable()) {
            return [];
        }

        $receiver = match (true) {
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $node->var,
            $node instanceof StaticCall && $node->class instanceof Name && $node->class->isSpecialClassName() => new Variable('this'),
            default => null,
        };
        // A call on what another call returns has its own effect at that inner call.
        $effects = $receiver !== null && self::readsChain($receiver) ? [['call', $node, $receiver]] : [];

        foreach ($node->getArgs() as $arg) {
            if (self::rootsAtThis($arg->value) && ! ($arg->value instanceof Variable)) {
                $effects[] = ['pass', $node, $arg->value];
            }
        }

        return $effects;
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
     * The chain on `$this` an expression writes or reads, keys stripped and in the spelling every alias shares; null
     * where a name, a call or the root is not one the source spells.
     *
     * @return list<string>|null
     */
    private function chainOf(Expr $expr): ?array
    {
        while ($expr instanceof ArrayDimFetch) {
            $expr = $expr->var;
        }

        $path = AnalysisScope::thisReadPath($expr);

        return $path === null ? null : $this->canonical($path);
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
