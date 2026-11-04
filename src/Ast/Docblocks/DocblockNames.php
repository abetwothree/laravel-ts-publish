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
     * Whether a class, interface, trait or enum of this name can be loaded.
     *
     * @phpstan-assert-if-true class-string $fqcn
     */
    public static function isClassLike(string $fqcn): bool
    {
        // An autoloader loads the file a name maps to whatever kind it declares, so one autoload attempt answers all.
        return class_exists($fqcn) || interface_exists($fqcn, false) || trait_exists($fqcn, false);
    }
}
