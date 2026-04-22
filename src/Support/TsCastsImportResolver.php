<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use Illuminate\Support\Str;

/**
 * Resolve TsCasts import statements and type-name collisions.
 *
 * @phpstan-type ResolvedImports = array{
 *     overrides: array<string, string>,
 *     importStatements: list<string>,
 * }
 */
class TsCastsImportResolver
{
    /**
     * Resolve import aliases for TsCasts overrides and generate import statements.
     *
     * @param  array<string, string>  $overrides
     * @param  array<string, string>  $importPaths
     * @return ResolvedImports
     */
    public function resolve(array $overrides, array $importPaths): array
    {
        /** @var array<int, array{prop: string, type: string, path: string, pairKey: string}> $entries */
        $entries = [];

        /** @var array<string, array<string, true>> $pathsByType */
        $pathsByType = [];

        /** @var array<string, array{type: string, path: string}> $pairs */
        $pairs = [];

        foreach ($importPaths as $prop => $path) {
            if (! isset($overrides[$prop])) {
                continue;
            }

            $type = $overrides[$prop];
            $pairKey = $type."\0".$path;

            $entries[] = [
                'prop' => $prop,
                'type' => $type,
                'path' => $path,
                'pairKey' => $pairKey,
            ];

            $pathsByType[$type][$path] = true;

            if (! isset($pairs[$pairKey])) {
                $pairs[$pairKey] = [
                    'type' => $type,
                    'path' => $path,
                ];
            }
        }

        // Pre-compute unique prefixes per type so that paths sharing the same basename
        // get more trailing segments until all prefixes in the conflict group are distinct.
        /** @var array<string, array<string, string>> $prefixMap type => (path => prefix) */
        $prefixMap = [];

        foreach ($pathsByType as $type => $paths) {
            if (count($paths) > 1) {
                $prefixMap[$type] = $this->computeUniquePrefixes(array_keys($paths));
            }
        }

        /** @var array<string, array{local: string, importName: string, path: string}> $resolvedByPair */
        $resolvedByPair = [];

        foreach ($pairs as $pairKey => $pair) {
            $type = $pair['type'];
            $path = $pair['path'];
            $hasConflict = count($pathsByType[$type] ?? []) > 1;

            if (! $hasConflict) {
                $resolvedByPair[$pairKey] = [
                    'local' => $type,
                    'importName' => $type,
                    'path' => $path,
                ];

                continue;
            }

            $prefix = $prefixMap[$type][$path] ?? $this->computePathPrefixAtDepth($path, 1);
            $alias = $prefix.$type;
            $resolvedByPair[$pairKey] = [
                'local' => $alias,
                'importName' => $type.' as '.$alias,
                'path' => $path,
            ];
        }

        $resolvedOverrides = $overrides;

        foreach ($entries as $entry) {
            $resolved = $resolvedByPair[$entry['pairKey']] ?? null;

            if ($resolved === null) {
                continue;
            }

            $resolvedOverrides[$entry['prop']] = $resolved['local'];
        }

        /** @var list<string> $importStatements */
        $importStatements = [];

        foreach ($resolvedByPair as $resolved) {
            $importStatements[] = "import type { {$resolved['importName']} } from '{$resolved['path']}';";
        }

        return [
            'overrides' => $resolvedOverrides,
            'importStatements' => $importStatements,
        ];
    }

    /**
     * Derive StudlyCase prefixes that are unique across a set of conflicting import paths.
     *
     * Incrementally uses more trailing path segments until every path in the group
     * maps to a distinct prefix, preventing duplicate alias identifiers in TypeScript.
     *
     * @param  list<string>  $paths
     * @return array<string, string> path => prefix
     */
    private function computeUniquePrefixes(array $paths): array
    {
        $segmentCounts = array_map(
            fn (string $path) => count(array_filter(explode('/', $path), fn (string $s) => $s !== '')),
            $paths
        );

        $maxDepth = max(1, ...$segmentCounts);

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $prefixes = [];

            foreach ($paths as $path) {
                $prefixes[$path] = $this->computePathPrefixAtDepth($path, $depth);
            }

            if (count(array_unique(array_values($prefixes))) === count($paths)) {
                return $prefixes;
            }
        }

        // Fallback: use all segments (distinct paths will produce distinct prefixes)
        $prefixes = [];

        foreach ($paths as $path) {
            $prefixes[$path] = $this->computePathPrefixAtDepth($path, $maxDepth);
        }

        return $prefixes;
    }

    /**
     * Derive a StudlyCase prefix from the last $depth segments of $path, stripping all extensions.
     *
     * Strips composite extensions (e.g. `.d.ts`, `.ts`, `.js`) from the final segment
     * before converting to StudlyCase.
     *
     * For example: depth=1, `@js/types/user-profile` → `UserProfile`
     * For example: depth=2, `@js/types/user-profile` → `TypesUserProfile`
     */
    private function computePathPrefixAtDepth(string $path, int $depth): string
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $s) => $s !== ''));
        $segments = array_slice($segments, -$depth);

        // Strip all extensions from the last segment (handles .d.ts, .ts, .js, etc.)
        $last = (string) array_pop($segments);
        $last = (string) preg_replace('/(\.[^.]+)+$/', '', $last);
        $segments[] = $last;

        return Str::studly(implode(' ', $segments));
    }
}
