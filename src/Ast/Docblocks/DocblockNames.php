<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use ReflectionClass;

/**
 * Resolves a name a docblock spells as the file that wrote it reads it, for every class-like kind.
 *
 * @internal
 */
final class DocblockNames
{
    /**
     * The name as `$context`'s file reads it: through its `use` imports, then its namespace, else as written.
     *
     * @param  ReflectionClass<object>  $context  the class or trait whose file wrote the name
     */
    public static function resolve(string $name, ReflectionClass $context): string
    {
        return LaravelTsPublish::resolveDocblockTypeName(
            $name,
            LaravelTsPublish::parseFileUseStatements($context),
            $context->getNamespaceName(),
        );
    }

    /**
     * Whether a name is a loadable class, interface or enum: a type a value can have, so never a trait.
     *
     * @phpstan-assert-if-true class-string $name
     */
    public static function isValueClass(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name);
    }

    /**
     * Whether a name is a loadable class, interface, trait or enum: anything a docblock may name.
     *
     * @phpstan-assert-if-true class-string $name
     */
    public static function isClassLike(string $name): bool
    {
        // An autoloader loads the file a name maps to whatever kind it declares, so one autoload attempt answers all.
        return class_exists($name) || interface_exists($name, false) || trait_exists($name, false);
    }
}
