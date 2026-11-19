<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Effects\Trust;

use AbeTwoThree\LaravelTsPublish\Ast\Effects\CallableTargets;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\MethodLocator;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use ReflectionClass;
use ReflectionMethod;

/**
 * A method that only hands back what it holds, as a model's getKeyName() override returning `'uuid'` does, so running
 * it changes nothing.
 *
 * @internal
 */
final class LiteralGetter
{
    /**
     * Whether a method's body is one `return` of a scalar, a constant, a class constant, a property its class declares
     * with no hook, or an array of those.
     */
    public static function returnsLiteral(ReflectionMethod $method): bool
    {
        $class = $method->getDeclaringClass();
        $body = resolve(MethodLocator::class)->locate($class->getName(), $method->getName())?->method->stmts ?? [];
        $statements = array_values(array_filter($body, static fn (Stmt $statement): bool => ! $statement instanceof Nop));

        return count($statements) === 1 && $statements[0] instanceof Return_ && $statements[0]->expr !== null
            && self::isLiteral($statements[0]->expr, $class);
    }

    /**
     * Whether reading an expression runs no code: a scalar, a constant, a class constant, a property of `$this` its
     * class declares with no hook, or an array of those.
     *
     * @param  ReflectionClass<object>  $class  the class whose method returns it
     */
    private static function isLiteral(Expr $expr, ReflectionClass $class): bool
    {
        return match (true) {
            $expr instanceof Scalar => ! $expr instanceof InterpolatedString,
            $expr instanceof ConstFetch => true,
            $expr instanceof ClassConstFetch => $expr->class instanceof Name && $expr->name instanceof Identifier,
            $expr instanceof PropertyFetch => CallableTargets::isThis($expr->var) && $expr->name instanceof Identifier
                && self::plainProperty($class, $expr->name->toString()),
            $expr instanceof Array_ => array_all($expr->items, static fn (ArrayItem $item): bool => ! $item->unpack
                && ! $item->byRef && ($item->key === null || self::isLiteral($item->key, $class))
                && self::isLiteral($item->value, $class)),
            default => false,
        };
    }

    /**
     * Whether `$this->{name}` reads a property a class declares, rather than running `__get()` or a get hook.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function plainProperty(ReflectionClass $class, string $name): bool
    {
        $property = $class->hasProperty($name) ? $class->getProperty($name) : null;

        return $property !== null && ! $property->isStatic() && ! $property->hasHooks();
    }
}
