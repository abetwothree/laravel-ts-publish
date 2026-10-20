<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Values\PropertyDocblockTypeReader;
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
     * Delegates to the canonical implementation on LaravelTsPublish.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaringClass
     */
    protected function resolveDocblockType(string $type, ReflectionClass $declaringClass): string
    {
        $useMap = LaravelTsPublish::parseFileUseStatements($declaringClass);
        $namespace = $declaringClass->getNamespaceName();

        return LaravelTsPublish::resolveDocblockTypeName($type, $useMap, $namespace);
    }

    /**
     * Resolve the FQCN of the type either declared on a property via native type hints or via a @var annotation in the docblock.
     *
     * Defaults to the `$resource` property (e.g. `/** @var MediaType|null *\/`).
     *
     * Each `@var` union member, `?` stripped, resolves through the file's imports, then the class's own namespace.
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

        // Check the property type from reflection
        // E.g. for `public MediaType $resource`, this would return `MediaType::class`
        // Note: JsonResource::$resource forbids child-class type narrowing, so this is unreachable for 'resource'.
        $info = LaravelTsPublish::propertyTypes($declaringClass, $property);
        if (count($info['classes']) > 0) {
            return $info['classes'][0]; // @codeCoverageIgnore
        }

        // If the property doesn't have a native type declaration, check for a @var annotation in the docblock
        $docComment = $declaringClass->getProperty($property)->getDocComment();
        $declared = $docComment === false
            ? null
            : resolve(PropertyDocblockTypeReader::class)->extractVarType($docComment);

        if ($declared === null || $declared === '') {
            return null; // @codeCoverageIgnore
        }

        // Split the @var value on | to handle union types in any order (e.g. null|Type or Type|null)
        $skip = ['null', 'mixed', 'false', 'true', 'bool', 'int', 'float', 'string',
            'array', 'object', 'void', 'iterable', 'callable', 'never', 'static', 'self'];

        foreach (LaravelTsPublish::splitPhpDocUnionType($declared) as $token) {
            $token = ltrim(trim($token), '?');

            if ($token === '' || in_array(strtolower($token), $skip, true)) {
                continue;
            }

            $fqcn = $this->resolveDocblockType($token, $declaringClass);

            if (class_exists($fqcn) || enum_exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
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
