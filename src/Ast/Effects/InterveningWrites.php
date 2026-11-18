<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\List_;
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
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * Whether the items of a literal between a group's keys may write the discriminant's read, so a guard after the write
 * may test another value than the discriminant sent.
 *
 * A write is one the items spell, or one a call on `$this` runs in the body it reaches one level down. A call whose
 * body cannot be read, a closure's call and a call on an object holding the read may write it too. On an API resource
 * `$this->x` reads `$this->resource->x`, so a write to either reaches both.
 *
 * @internal
 */
final class InterveningWrites
{
    /**
     * Whether the items may write the read: $items as they spell writes, and $calling, the items no group's key
     * holds, through the calls they make.
     *
     * @param  list<Node>  $items  every item from the group's first key to its last
     * @param  list<Node>  $calling  the items among them that hold no key of the group
     */
    public static function reach(array $items, array $calling, ReadPath $read, AnalysisScope $scope): bool
    {
        $proxied = $scope->hasResourceProxy();
        $writes = static fn (Node $node): bool => array_any(
            self::writtenPaths($node),
            static fn (ReadPath $written): bool => self::reaches($read, $written, $proxied),
        );

        return new NodeFinder()->findFirst($items, $writes) !== null
            || new NodeFinder()->findFirst($calling, static fn (Node $node): bool => $node instanceof CallLike
                && ! $node->isFirstClassCallable()
                && self::callMayWrite($node, $read, $scope, $proxied)) !== null;
    }

    /**
     * Whether a call may write the read: through the body a call on `$this` reaches, unless it is a resource helper,
     * through a closure, or as a method of an object holding the read.
     */
    private static function callMayWrite(CallLike $call, ReadPath $read, AnalysisScope $scope, bool $proxied): bool
    {
        if ($call instanceof FuncCall) {
            return ! $call->name instanceof Name;
        }

        if ($call instanceof StaticCall) {
            $class = $scope->staticCallClass($call);

            return $class !== null && self::bodyMayWrite($class, $call, $read, $scope, $proxied);
        }

        if (! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall) {
            return false;
        }

        $receiver = ReadPath::of($call->var);

        if ($receiver?->root === 'this' && $receiver->segments === []) {
            return ! $call->name instanceof Identifier || self::bodyMayWrite(
                $scope->thisMemberClass($call->name->toString(), true),
                $call,
                $read,
                $scope,
                $proxied,
            );
        }

        return $receiver !== null && self::reaches($read, $receiver, $proxied);
    }

    /**
     * Whether the method a call names on a class may write the read: one the class lacks or whose body cannot be
     * read may, a resource helper never does, and any other does where its body writes `$this` at the read.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function bodyMayWrite(
        ReflectionClass $class,
        MethodCall|NullsafeMethodCall|StaticCall $call,
        ReadPath $read,
        AnalysisScope $scope,
        bool $proxied,
    ): bool {
        $name = $call->name;

        if (! $name instanceof Identifier || ! $class->hasMethod($name->toString())) {
            return true;
        }

        if (ReadOnlyCalls::resourceHelper($call, $scope) !== null) {
            return false;
        }

        $body = resolve(MethodLocator::class)->locate($class->getName(), $name->toString())?->method->stmts;
        $writesThis = static fn (ReadPath $written): bool => $written->root === 'this'
            && self::reaches($read, $written, $proxied);

        return $body === null || new NodeFinder()->findFirst(
            $body,
            static fn (Node $node): bool => array_any(self::writtenPaths($node), $writesThis),
        ) !== null;
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

        return array_values(array_filter(array_merge(...array_map(self::targetPaths(...), $targets))));
    }

    /**
     * The paths one write target names: the longest path it starts with, or each of a destructuring's targets.
     *
     * @return list<ReadPath|null>
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

        return [$path];
    }

    /**
     * Whether a write to one path reaches a read: the path is the read, or one the read is a member of at any depth,
     * whatever operator each step is read with, since PHP writes through `->` alone.
     */
    private static function reaches(ReadPath $read, ReadPath $written, bool $proxied): bool
    {
        $target = self::steps($written, $proxied);

        return array_slice(self::steps($read, $proxied), 0, count($target)) === $target;
    }

    /**
     * A path's root and segments, with `$this->resource->` read as `$this->` where an API resource forwards one to
     * the other.
     *
     * @return list<string>
     */
    private static function steps(ReadPath $path, bool $proxied): array
    {
        $segments = $proxied && $path->root === 'this' && ($path->segments[0] ?? null) === 'resource'
            ? array_slice($path->segments, 1)
            : $path->segments;

        return [$path->root, ...$segments];
    }
}
