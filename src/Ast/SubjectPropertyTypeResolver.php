<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use ReflectionClass;

/**
 * Resolve one of a class's own properties to a value result — `@var` docblock first, native declared
 * type second — for the model-less "subject mode" the engine uses outside resources.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class SubjectPropertyTypeResolver
{
    /**
     * Resolve a declared property's TypeScript type and FQCN channels, or null when neither source
     * yields a type whose tokens can be imported.
     *
     * @param  ReflectionClass<object>  $subject
     * @return ValueExpressionResult|null
     */
    public function resolve(ReflectionClass $subject, string $name): ?array
    {
        if (! $subject->hasProperty($name)) {
            return null;
        }

        return resolve(PropertyDocblockTypeReader::class)->read($subject->getProperty($name))
            ?? resolve(ReflectedTypeAcceptor::class)->accept(LaravelTsPublish::propertyTypes($subject, $name));
    }
}
