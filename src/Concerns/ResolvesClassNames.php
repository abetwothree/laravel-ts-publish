<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Parses PHP `use` statements and resolves docblock type names to their FQCNs.
 */
trait ResolvesClassNames
{
    /** @var array<class-string, array<string, class-string>> */
    protected ?array $cachedUseStatements = [];

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
     * Short names are resolved to FQCNs using the file's use-statement map.
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
        if ($docComment === false || ! preg_match('/@var\s+([^\s*]+)/', $docComment, $m)) {
            return null; // @codeCoverageIgnore
        }

        // Split the @var value on | to handle union types in any order (e.g. null|Type or Type|null)
        $skip = ['null', 'mixed', 'false', 'true', 'bool', 'int', 'float', 'string',
            'array', 'object', 'void', 'iterable', 'callable', 'never', 'static', 'self'];
        $useStatements = $this->resolveUseStatements($declaringClass);

        foreach (explode('|', $m[1]) as $token) {
            $token = trim($token);

            if ($token === '' || in_array(strtolower($token), $skip, true)) {
                continue;
            }

            foreach ($useStatements as $alias => $fqcn) {
                if ($alias === $token && (class_exists($fqcn) || enum_exists($fqcn))) {
                    return $fqcn;
                }
            }

            // @codeCoverageIgnoreStart
            if (class_exists($token) || enum_exists($token)) {
                return $token;
            }
            // @codeCoverageIgnoreEnd
        }

        return null;
    }

    /**
     * Parse the use statements from the resource's source file.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaringClass
     * @return array<string, class-string> alias => fully-qualified class name
     */
    protected function resolveUseStatements(ReflectionClass $declaringClass): array
    {
        $className = $declaringClass->getName();
        if (isset($this->cachedUseStatements[$className])) {
            return $this->cachedUseStatements[$className];
        }

        $filePath = (string) $declaringClass->getFileName();
        $source = (string) file_get_contents($filePath);
        $stmts = $this->parseAndResolveAst($source);

        $finder = new NodeFinder;
        /** @var array<string, class-string> */
        $map = [];

        foreach ($finder->find($stmts, fn (Node $n) => $n instanceof Use_) as $useNode) {
            if (! $useNode instanceof Use_) {
                continue; // @codeCoverageIgnore
            }

            foreach ($useNode->uses as $use) {
                $alias = $use->alias !== null ? $use->alias->toString() : $use->name->getLast();
                /** @var class-string $class */
                $class = $use->name->toString();
                $map[$alias] = $class;
            }
        }

        $this->cachedUseStatements[$className] = $map;

        return $this->cachedUseStatements[$className];
    }

    /**
     * Parse PHP source and resolve fully qualified names via AST traversal.
     *
     * @return array<Node>
     */
    protected function parseAndResolveAst(string $source): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $stmts = $parser->parse($source);

        if ($stmts === null) {
            return []; // @codeCoverageIgnore
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);

        return $traverser->traverse($stmts);
    }
}
