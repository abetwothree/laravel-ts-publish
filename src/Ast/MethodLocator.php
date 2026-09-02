<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use ReflectionClass;

/**
 * Finds a method's ClassMethod AST node, memoized, recording every parsed file as a cache dependency.
 */
class MethodLocator
{
    /** @var array<string, MethodContext|null> */
    protected array $located = [];

    public function __construct(protected AstParser $parser) {}

    /**
     * Locate a method in the class's OWN file only — an inherited method is deliberately a miss,
     * which is how callers detect delegation/inheritance cases.
     *
     * Callers pass names straight from route action strings, so the AST is searched for the name as
     * declared rather than as spelled; PHP dispatches either, and a mismatch would silently type nothing.
     */
    public function locateOwn(string $class, string $method): ?MethodContext
    {
        return $this->memo('own:'.$class.'::'.strtolower($method), function () use ($class, $method): ?MethodContext {
            if (! class_exists($class)) {
                return null;
            }

            /** @var ReflectionClass<object> $reflection */
            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod($method)) {
                return null;
            }

            $file = $reflection->getFileName();

            if ($file === false) {
                return null;
            }

            $declaredName = $reflection->getMethod($method)->getName();

            return $this->findIn($reflection, (string) $file, $declaredName, caseSensitive: true);
        });
    }

    /**
     * Locate a method wherever it is declared (class, trait, or parent), matching case-insensitively
     * to mirror PHP's own method dispatch.
     */
    public function locate(string $class, string $method): ?MethodContext
    {
        return $this->memo('any:'.$class.'::'.strtolower($method), function () use ($class, $method): ?MethodContext {
            if (! class_exists($class)) {
                return null;
            }

            /** @var ReflectionClass<object> $reflection */
            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod($method)) {
                return null;
            }

            $file = $reflection->getMethod($method)->getFileName();

            if ($file === false) {
                return null;
            }

            return $this->findIn($reflection, (string) $file, $method, caseSensitive: false);
        });
    }

    /**
     * Run the finder over the file's owning ClassLike for the named ClassMethod with a non-null body.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected function findIn(ReflectionClass $reflection, string $file, string $method, bool $caseSensitive): ?MethodContext
    {
        $stmts = $this->parser->parseFile($file);
        $finder = new NodeFinder;

        $matches = function (Node $node) use ($method, $caseSensitive): bool {
            return $node instanceof ClassMethod && ($caseSensitive
                ? $node->name->toString() === $method
                : strcasecmp($node->name->toString(), $method) === 0);
        };

        /** @var list<ClassLike> $owners */
        $owners = array_values(array_filter(
            $finder->findInstanceOf($stmts, ClassLike::class),
            fn (ClassLike $classLike): bool => $finder->findFirst($classLike->stmts, $matches) !== null,
        ));

        $owner = $this->resolveOwner($owners, $reflection, $method);

        if (! $owner instanceof ClassLike) {
            return null;
        }

        /** @var ClassMethod|null $node */
        $node = $finder->findFirst($owner->stmts, $matches);

        if (! $node instanceof ClassMethod || $node->stmts === null) {
            return null;
        }

        return new MethodContext($reflection, $node, $stmts);
    }

    /**
     * Memoize both hits and misses so repeated lookups never re-parse.
     *
     * @param  callable(): ?MethodContext  $resolve
     */
    protected function memo(string $key, callable $resolve): ?MethodContext
    {
        if (array_key_exists($key, $this->located)) {
            return $this->located[$key];
        }

        return $this->located[$key] = $resolve();
    }

    /**
     * One declaring ClassLike is unambiguous. Two or more — e.g. two classes sharing a file and a
     * method name — are disambiguated by the class PHP itself would dispatch $method to; a trait's
     * using class never appears in the trait's own file, so an unmatched tie falls back to the first.
     *
     * @param  list<ClassLike>  $owners
     * @param  ReflectionClass<object>  $reflection
     */
    private function resolveOwner(array $owners, ReflectionClass $reflection, string $method): ?ClassLike
    {
        if (count($owners) < 2) {
            return $owners[0] ?? null;
        }

        $expected = $reflection->getMethod($method)->getDeclaringClass()->getName();

        foreach ($owners as $owner) {
            if ($owner->namespacedName?->toString() === $expected) {
                return $owner;
            }
        }

        return $owners[0];
    }
}
