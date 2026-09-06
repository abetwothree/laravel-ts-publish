<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Shared php-parser call predicates: matching static calls and reading typed `$this` property classes.
 *
 * @internal
 */
class CallMatcher
{
    /**
     * Whether the node is a StaticCall on a class name ending with $classSuffix, calling $method.
     */
    public function isStaticCallTo(Node $node, string $classSuffix, string $method): bool
    {
        return $node instanceof StaticCall
            && $node->class instanceof Name
            && str_ends_with($node->class->toString(), $classSuffix)
            && $node->name instanceof Identifier
            && $node->name->toString() === $method;
    }

    /**
     * The statically-known method name of a MethodCall, StaticCall, or NullsafeMethodCall, else null.
     */
    public function methodCallName(Node $node): ?string
    {
        if ($node instanceof MethodCall || $node instanceof StaticCall || $node instanceof NullsafeMethodCall) {
            return $node->name instanceof Identifier ? $node->name->toString() : null;
        }

        return null;
    }

    /**
     * Resolve the class of a `$this->property` reference from its typed property declaration.
     *
     * @param  ReflectionClass<object>  $reflection
     * @return class-string|null
     */
    public function resolveThisPropertyClass(ReflectionClass $reflection, Expr $expr): ?string
    {
        if (! $expr instanceof PropertyFetch || ! $expr->var instanceof Variable || $expr->var->name !== 'this') {
            return null;
        }

        if (! $expr->name instanceof Identifier) {
            return null;
        }

        $property = $expr->name->toString();

        if (! $reflection->hasProperty($property)) {
            return null;
        }

        $type = $reflection->getProperty($property)->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();

        if (! class_exists($class)) {
            return null;
        }

        /** @var class-string $class */
        return $class;
    }
}
