<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
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
     * $subject is the class the property was read on: a trait it or a class between it and the reported declaring class
     * uses can redeclare the property, which PHP 8.4's reflection reports as the parent's.
     *
     * @param  ReflectionClass<object>|null  $subject
     * @return ReflectionClass<object>
     */
    public static function ofProperty(ReflectionProperty $property, ?ReflectionClass $subject = null): ReflectionClass
    {
        $redeclaring = $subject === null ? null : self::redeclaringTrait($property, $subject);

        if ($redeclaring !== null) {
            return $redeclaring;
        }

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
     * The trait a class from $subject up to, not including, the property's reported declaring class uses to redeclare
     * the property, or null when none does.
     *
     * PHP 8.4 reports a trait's redeclaration of a parent's property as the parent's, docblock included; PHP 8.5 does
     * not, so there the reported declaring class is already the nearest.
     *
     * @param  ReflectionClass<object>  $subject
     * @return ReflectionClass<object>|null
     */
    public static function redeclaringTrait(ReflectionProperty $property, ReflectionClass $subject): ?ReflectionClass
    {
        $declaring = $property->getDeclaringClass()->getName();
        $name = $property->getName();

        for ($class = $subject; $class !== false && $class->getName() !== $declaring; $class = $class->getParentClass()) {
            $trait = array_find(
                self::traits($class),
                fn (ReflectionClass $trait): bool => $trait->hasProperty($name) && self::declaresProperty($trait, $name),
            );

            if ($trait !== null) {
                return $trait;
            }
        }

        return null;
    }

    /**
     * A property's docblock as a class's or trait's own source writes it, on the property statement or the promoted
     * parameter, or false when it has none.
     *
     * @param  ReflectionClass<object>  $class
     */
    public static function propertyDocComment(ReflectionClass $class, string $name): string|false
    {
        return self::propertyNode($class, $name)?->getDocComment()?->getText() ?? false;
    }

    /**
     * Whether a class's or trait's own source declares a property, as a property statement or a promoted parameter.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function declaresProperty(ReflectionClass $class, string $name): bool
    {
        return self::propertyNode($class, $name) !== null;
    }

    /**
     * The property statement or promoted parameter a class's or trait's own source declares a property with.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function propertyNode(ReflectionClass $class, string $name): Property|Param|null
    {
        $file = $class->getFileName();
        $node = $file === false ? null : new NodeFinder()->findFirst(
            resolve(AstParser::class)->parseFile($file),
            fn (Node $node): bool => $node instanceof ClassLike && $node->namespacedName?->toString() === $class->getName(),
        );

        if (! $node instanceof ClassLike) {
            return null; // @codeCoverageIgnore
        }

        return $node->getProperty($name) ?? array_find(
            $node->getMethod('__construct')->params ?? [],
            fn (Param $param): bool => $param->isPromoted() && $param->var instanceof Variable && $param->var->name === $name,
        );
    }
}
