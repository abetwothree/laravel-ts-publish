<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;

/**
 * Whether a framework method only reads as an instance of the runtime class runs it: PHP looks most `$this` calls in
 * its body, and the magic methods a `$this` property no declaration reaches runs, up on the runtime class, so a method
 * the app overrides there runs in place of the framework's, as a resource's own `when()` does inside `unless()`.
 *
 * @phpstan-type Dispatched = array{ReflectionClass<object>, ReflectionMethod}
 * @phpstan-type UserMethod = array{ReflectionClass<object>, string}
 * @phpstan-type UserJudge = Closure(ReflectionClass<object>, string): bool
 * @phpstan-type InPlaceUse = array{Expr, list<string>}
 *
 * @internal
 */
final class FrameworkDispatch
{
    /**
     * Whether a method the family declares only reads where an instance of the runtime class runs it: each method of
     * the family it dispatches to does in turn, each call it cannot name is one the family trusts, and $userMayWrite,
     * or the family's own judge where it is null, reads each method of the app it reaches as writing nothing.
     *
     * @param  ReflectionClass<object>  $runtime
     * @param  UserJudge|null  $userMayWrite  whether running a method the app declares, on a class, may write
     */
    public static function trusted(
        FrameworkFamily $family,
        ReflectionMethod $method,
        ReflectionClass $runtime,
        ?Closure $userMayWrite = null,
    ): bool {
        if (! $family->declares($method)) {
            return false;
        }

        if ($userMayWrite !== null) {
            return self::judged($family, $method, $runtime, $userMayWrite);
        }

        return resolve(AnalysisMemo::class)->rememberFact(
            'framework-trust:'.self::key($family, $method, $runtime),
            static fn (): bool => self::judged($family, $method, $runtime, $family->userMethodMayWrite(...)),
        );
    }

    /**
     * Whether a framework method dispatches to no code the walk cannot follow and to no method of the app the judge
     * reads as one that may write.
     *
     * @param  ReflectionClass<object>  $runtime
     * @param  UserJudge  $userMayWrite
     */
    private static function judged(
        FrameworkFamily $family,
        ReflectionMethod $method,
        ReflectionClass $runtime,
        Closure $userMayWrite,
    ): bool {
        $users = resolve(AnalysisMemo::class)->rememberFact(
            'framework-dispatch:'.self::key($family, $method, $runtime),
            static fn (): ?array => self::userMethods($family, $method, $runtime),
        );

        return $users !== null
            && ! array_any($users, static fn (array $user): bool => $userMayWrite($user[0], $user[1]));
    }

    /**
     * The methods the app declares that a framework method reaches on the runtime class, each with the class it runs
     * on, through every method of the family it dispatches to; null where it reaches code the walk cannot follow: a
     * body the locator cannot read, or, in a family that trusts none, a call whose method the walk cannot name.
     *
     * @param  ReflectionClass<object>  $runtime
     * @return list<UserMethod>|null
     */
    private static function userMethods(FrameworkFamily $family, ReflectionMethod $method, ReflectionClass $runtime): ?array
    {
        $pending = [[$method->getDeclaringClass(), $method]];
        $seen = [];
        $users = [];

        while (($next = array_pop($pending)) !== null) {
            [$class, $current] = $next;
            $key = $current->getDeclaringClass()->getName().'::'.strtolower($current->getName());

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (! $family->declares($current)) {
                $users[] = [$class, $current->getName()];

                continue;
            }

            $dispatched = self::dispatched($current, $runtime);

            if ($dispatched === null || (in_array(null, $dispatched, true) && ! $family->trustsComputedCalls())) {
                return null;
            }

            array_push($pending, ...array_filter($dispatched, static fn (?array $one): bool => $one !== null));
        }

        return $users;
    }

    /**
     * The methods a method's body runs on `$this`, magic ones included, each with the class PHP looks it up on, null
     * for a call whose method the source does not name or the class lacks, which a magic method runs; null where the
     * body cannot be read.
     *
     * @param  ReflectionClass<object>  $runtime
     * @return list<Dispatched|null>|null
     */
    private static function dispatched(ReflectionMethod $method, ReflectionClass $runtime): ?array
    {
        $declaring = $method->getDeclaringClass();
        $body = resolve(MethodLocator::class)->locate($declaring->getName(), $method->getName())?->method->stmts;

        if ($body === null) {
            return null;
        }

        $finder = new NodeFinder;
        /** @var list<MethodCall|NullsafeMethodCall|StaticCall> $calls */
        $calls = $finder->find($body, static fn (Node $node): bool => $node instanceof MethodCall
            || $node instanceof NullsafeMethodCall || $node instanceof StaticCall);
        $called = [];

        foreach ($calls as $call) {
            $name = $call->name instanceof Identifier ? $call->name->toString() : null;
            $class = AnalysisScope::thisCallClassOf($call, $name, $declaring, $runtime);

            if ($class !== null) {
                $called[] = $name !== null && $class->hasMethod($name) ? [$class, $class->getMethod($name)] : null;
            }
        }

        return [...$called, ...self::magicRuns($finder->find($body, static fn (): bool => true), $declaring, $runtime)];
    }

    /**
     * The magic methods of the runtime class some nodes run through a `$this` property no declaration reaches:
     * `__get()`, or, where a node uses the property in place, the methods usedInPlace() names.
     *
     * @param  array<Node>  $nodes  every node of a body
     * @param  ReflectionClass<object>  $declaring
     * @param  ReflectionClass<object>  $runtime
     * @return list<Dispatched>
     */
    private static function magicRuns(array $nodes, ReflectionClass $declaring, ReflectionClass $runtime): array
    {
        $inPlace = [];

        foreach ($nodes as $node) {
            foreach (self::usedInPlace($node) as [$target, $magic]) {
                $inPlace[spl_object_id($target)] = $magic;
            }
        }

        $runs = [];

        foreach (array_filter($nodes, static fn (Node $node): bool => self::magicProperty($node, $declaring, $runtime)) as $read) {
            foreach ($inPlace[spl_object_id($read)] ?? ['__get'] as $magic) {
                if ($runtime->hasMethod($magic)) {
                    $runs[] = [$runtime, $runtime->getMethod($magic)];
                }
            }
        }

        return $runs;
    }

    /**
     * The operands a node uses in place, each with the magic methods PHP runs for one that is a property no
     * declaration reaches: isset() runs `__isset()`, empty() `__isset()` and `__get()`, unset() `__unset()`, an
     * assignment `__set()`, and a compound assignment or an increment `__get()` and `__set()`.
     *
     * @return list<InPlaceUse>
     */
    private static function usedInPlace(Node $node): array
    {
        return match (true) {
            $node instanceof Isset_ => array_values(array_map(static fn (Expr $var): array => [$var, ['__isset']], $node->vars)),
            $node instanceof Empty_ => [[$node->expr, ['__isset', '__get']]],
            $node instanceof Unset_ => array_values(array_map(static fn (Expr $var): array => [$var, ['__unset']], $node->vars)),
            $node instanceof Assign => [[$node->var, ['__set']]],
            $node instanceof AssignOp, $node instanceof AssignRef, $node instanceof PreInc, $node instanceof PreDec,
            $node instanceof PostInc, $node instanceof PostDec => [[$node->var, ['__get', '__set']]],
            default => [],
        };
    }

    /**
     * Whether a node is a property of `$this` PHP reaches through a magic method from the declaring class's body: one
     * whose name the source does not spell, or one the class lacks, holds static or keeps private to another class.
     *
     * @param  ReflectionClass<object>  $declaring
     * @param  ReflectionClass<object>  $runtime
     */
    private static function magicProperty(Node $node, ReflectionClass $declaring, ReflectionClass $runtime): bool
    {
        if (! ($node instanceof PropertyFetch || $node instanceof NullsafePropertyFetch) || ! ReadPath::isThis($node->var)) {
            return false;
        }

        if (! $node->name instanceof Identifier) {
            return true;
        }

        $name = $node->name->toString();
        $class = AnalysisScope::memberClassOf($declaring, $runtime, $name, false);
        $property = $class->hasProperty($name) ? $class->getProperty($name) : null;

        return $property === null || $property->isStatic()
            || ($property->isPrivate() && $property->getDeclaringClass()->getName() !== $declaring->getName());
    }

    /**
     * The key a walk's answers are remembered by: the family, the runtime class and the method's declaration.
     *
     * @param  ReflectionClass<object>  $runtime
     */
    private static function key(FrameworkFamily $family, ReflectionMethod $method, ReflectionClass $runtime): string
    {
        return $family->name.':'.$runtime->getName().':'.$method->getDeclaringClass()->getName().'::'
            .strtolower($method->getName());
    }
}
