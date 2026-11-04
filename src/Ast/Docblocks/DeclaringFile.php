<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Finds the class or trait whose file declares a member, so its docblock resolves against that file's imports.
 *
 * Reflection reports a trait's method or property as declared by the class using the trait, though its file and
 * docblock are the trait's.
 *
 * @internal
 */
final class DeclaringFile
{
    /**
     * The class or trait whose file declares a property, as a property statement or a promoted parameter.
     *
     * @return ReflectionClass<object>
     */
    public static function ofProperty(ReflectionProperty $property): ReflectionClass
    {
        $class = $property->getDeclaringClass();
        $name = $property->getName();
        $holders = array_filter(self::traits($class), fn (ReflectionClass $trait): bool => $trait->hasProperty($name));

        // Reflection cannot tell a class's redeclaration of its trait's property from the trait's, docblock included.
        if ($holders === [] || self::declaresProperty($class, $name)) {
            return $class;
        }

        return array_find($holders, fn (ReflectionClass $trait): bool => self::declaresProperty($trait, $name))
            ?? $class;
    }

    /**
     * The class or trait whose file declares a method's body.
     *
     * @return ReflectionClass<object>
     */
    public static function ofMethod(ReflectionMethod $method): ReflectionClass
    {
        $class = $method->getDeclaringClass();
        $file = $method->getFileName();

        if ($file === false || $file === $class->getFileName()) {
            return $class;
        }

        return array_find(self::traits($class), fn (ReflectionClass $trait): bool => $trait->getFileName() === $file)
            ?? $class;
    }

    /**
     * Every trait a class uses, each followed by the traits it uses in turn.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    public static function traits(ReflectionClass $class): array
    {
        $traits = [];

        foreach ($class->getTraits() as $trait) {
            $traits[] = $trait;
            array_push($traits, ...self::traits($trait));
        }

        return $traits;
    }

    /**
     * Whether a class's or trait's own source declares a property, as a property statement or a promoted parameter.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function declaresProperty(ReflectionClass $class, string $name): bool
    {
        $file = $class->getFileName();
        $node = $file === false ? null : new NodeFinder()->findFirst(
            resolve(AstParser::class)->parseFile($file),
            fn (Node $node): bool => $node instanceof ClassLike && $node->namespacedName?->toString() === $class->getName(),
        );

        if (! $node instanceof ClassLike) {
            return false; // @codeCoverageIgnore
        }

        return $node->getProperty($name) !== null || array_any(
            $node->getMethod('__construct')->params ?? [],
            fn (Param $param): bool => $param->isPromoted() && $param->var instanceof Variable && $param->var->name === $name,
        );
    }
}
