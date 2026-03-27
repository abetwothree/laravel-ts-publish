<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Concerns;

use Illuminate\Support\Str;
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
     * Handles:
     * - `\Fully\Qualified\Name` — strip leading backslash
     * - `ShortName` — look up in use map, then try current namespace prefix
     * - `?T` — resolve T recursively and re-prepend `?`
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaringClass
     */
    protected function resolveDocblockType(string $type, ReflectionClass $declaringClass): string
    {
        // ?T nullable shorthand → resolve inner type, re-prepend ?
        if (str_starts_with($type, '?')) {
            return '?'.$this->resolveDocblockType(substr($type, 1), $declaringClass);
        }

        // Fully qualified with leading backslash → strip the backslash
        if (str_starts_with($type, '\\')) {
            return substr($type, 1); // @codeCoverageIgnore
        }

        $fileName = $declaringClass->getFileName();
        if ($fileName !== false) {
            $useMap = $this->parseUseStatements((string) file_get_contents($fileName));

            // The root segment of the name is what appears in the use statement
            $root = Str::before($type, '\\');
            if (isset($useMap[$root])) {
                $rest = Str::after($type, '\\');

                return $rest !== $type ? $useMap[$root].'\\'.Str::after($type, '\\') : $useMap[$root];
            }
        }

        // Fall back to the declaring class's own namespace
        $namespace = $declaringClass->getNamespaceName();
        if ($namespace !== '') {
            $qualified = $namespace.'\\'.$type;
            if (class_exists($qualified)) {
                return $qualified;
            }
        }

        // Already directly resolvable (PHP built-ins and global-namespace classes)
        if (class_exists($type)) {
            return $type;
        }

        return $type;
    }

    /**
     * Parse `use` statements from PHP source and return a map of short name (or alias) → FQCN.
     *
     * @return array<string, string>
     */
    protected function parseUseStatements(string $source): array
    {
        $map = [];

        preg_match_all(
            '/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $alias = $match[2] ?? '';
            $short = $alias !== '' ? $alias : class_basename($fqcn);
            $map[$short] = $fqcn;
        }

        return $map;
    }
}
