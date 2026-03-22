<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use Illuminate\Support\Str;

/**
 * Shared import conflict resolution helpers for transformers.
 *
 * Provides alias formatting, const import formatting, and namespace prefix
 * computation used by both ModelTransformer and ResourceTransformer.
 */
trait ResolvesImportConflicts
{
    /** @var array<string, string> FQCN => aliased TypeScript name (only for conflicting imports) */
    protected array $importAliases = [];

    /** @var array<string, string> FQCN => aliased TypeScript const name (only for conflicting imports) */
    protected array $constImportAliases = [];

    /**
     * Format an import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatImportName(string $fqcn, string $typeName): string
    {
        $alias = $this->importAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $typeName) {
            return $typeName.' as '.$alias;
        }

        return $typeName;
    }

    /**
     * Format a const import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatConstImportName(string $fqcn): string
    {
        $constName = $this->enumConstMap[$fqcn];
        $alias = $this->constImportAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $constName) {
            return $constName.' as '.$alias;
        }

        return $constName;
    }

    /**
     * Compute a distinguishing namespace prefix for alias generation.
     *
     * Strips the configured namespace prefix, removes the class name, walks
     * backwards skipping common segments, and returns the StudlyCase of the
     * first meaningful segment.
     *
     * @param  list<string>  $skip  Namespace segments to skip (e.g. ['Models', 'Enums', 'App'])
     */
    protected function computeNamespacePrefix(string $fqcn, array $skip = ['Models', 'Enums', 'App']): string
    {
        $namespace = Str::beforeLast($fqcn, '\\');

        $prefix = config()->string('ts-publish.namespace_strip_prefix', '');

        if ($prefix !== '' && str_starts_with($namespace, $prefix)) {
            $namespace = substr($namespace, strlen($prefix));
        }

        $segments = array_filter(explode('\\', $namespace));

        // Walk backwards to find the first meaningful segment
        foreach (array_reverse($segments) as $segment) {
            if (! in_array($segment, $skip, true)) {
                return Str::studly($segment);
            }
        }

        // Fallback: use the first available segment
        $first = reset($segments);

        return $first !== false ? Str::studly($first) : '';
    }
}
