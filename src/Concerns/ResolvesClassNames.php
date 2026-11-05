<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\DocblockNames;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use PhpParser\Node;
use ReflectionClass;

/**
 * Parses PHP `use` statements and resolves docblock type names to their FQCNs.
 */
trait ResolvesClassNames
{
    /**
     * Resolve a type name from a docblock to its FQCN by consulting the declaring class's
     * `use` imports, its own namespace, and then falling back to the original name.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaringClass
     */
    protected function resolveDocblockType(string $type, ReflectionClass $declaringClass): string
    {
        return DocblockNames::resolve($type, $declaringClass);
    }

    /**
     * Resolve the FQCN of the type either declared on a property via native type hints or via a @var annotation in the docblock.
     *
     * Defaults to the `$resource` property (e.g. `/** @var MediaType|null *\/`).
     *
     * The first class or enum arm of its `@var` union, as DeclaredClasses::docblockArms() resolves it through the file
     * that declares the property, which PropertyDocblockTypeReader::tag() finds, a parent's or a trait's.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaringClass
     * @param  string  $property  The property name to inspect (default: 'resource')
     * @return class-string|null
     */
    protected function resolveClassOnProperty(ReflectionClass $declaringClass, string $property = 'resource'): ?string
    {
        if (! $declaringClass->hasProperty($property)) {
            return null; // @codeCoverageIgnore
        }

        $reflection = $declaringClass->getProperty($property);
        $declaring = $reflection->getDeclaringClass()->getName();

        // JsonResource::$resource forbids child-class type narrowing, so this is unreachable for 'resource'.
        $native = DeclaredClasses::nativeClasses($reflection->getType(), $declaringClass->getName(), $declaring);

        if ($native !== null) {
            return $native[0]; // @codeCoverageIgnore
        }

        $tag = resolve(PropertyDocblockTypeReader::class)->tag($reflection, $declaringClass);

        if ($tag === null) {
            return null; // @codeCoverageIgnore
        }

        $arms = DeclaredClasses::docblockArms($tag['type'], $tag['context'], $declaringClass->getName(), $declaring);

        return array_find($arms['classes'], fn (string $class): bool => ! interface_exists($class));
    }

    /**
     * Parse the use statements from the resource's source file.
     *
     * Delegates to the canonical, file-path-cached implementation on LaravelTsPublish.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaringClass
     * @return array<string, class-string> alias => fully-qualified class name
     */
    protected function resolveUseStatements(ReflectionClass $declaringClass): array
    {
        return LaravelTsPublish::parseFileUseStatements($declaringClass);
    }

    /**
     * Parse PHP source and resolve fully qualified names via AST traversal.
     *
     * @return array<Node>
     */
    protected function parseAndResolveAst(string $source): array
    {
        return resolve(AstParser::class)->parseSource($source);
    }
}
