<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Support\Str;

/**
 * @phpstan-import-type TypesImportMap from Datable
 *
 * Resolve TsCasts type imports and type-name collisions.
 *
 * @phpstan-type ResolvedImports = array{
 *     overrides: array<string, string>,
 *     typeImports: TypesImportMap,
 * }
 */
class TsCastsImportResolver
{
    /**
     * Resolve import aliases for TsCasts overrides and collect their type imports.
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
            $resolvedOverrides[$entry['prop']] = $resolvedByPair[$entry['pairKey']]['local'];
        }

        /** @var TypesImportMap $typeImports */
        $typeImports = [];

        foreach ($resolvedByPair as $resolved) {
            $typeImports[$resolved['path']][] = $resolved['importName'];
        }

        $typeImports = array_map(
            static function (array $types): array {
                sort($types);

                return $types;
            },
            $typeImports,
        );

        ksort($typeImports);

        return [
            'overrides' => $resolvedOverrides,
            'typeImports' => $typeImports,
        ];
    }

    /**
     * Derive StudlyCase prefixes that are unique across a set of conflicting import paths.
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

        $prefixes = [];

        foreach ($paths as $path) {
            $prefixes[$path] = $this->computePathPrefixAtDepth($path, $maxDepth);
        }

        // Extension stripping can still collide at max depth: '@types/auth.ts' and
        // '@types/auth.d.ts' both reduce to 'TypesAuth', so number the survivors.
        $grouped = [];

        foreach ($prefixes as $path => $prefix) {
            $grouped[$prefix][] = $path;
        }

        foreach ($grouped as $prefix => $groupPaths) {
            if (count($groupPaths) > 1) {
                foreach ($groupPaths as $i => $path) {
                    $prefixes[$path] = $prefix.($i + 1);
                }
            }
        }

        return $prefixes;
    }

    /**
     * Derive a StudlyCase prefix from the last $depth segments of $path, stripping all extensions.
     *
     * depth=1, `@js/types/user-profile` → `UserProfile`; depth=2 → `TypesUserProfile`.
     */
    private function computePathPrefixAtDepth(string $path, int $depth): string
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $s) => $s !== ''));
        $segments = array_slice($segments, -$depth);

        // Composite extensions such as `.d.ts` must go too, not just the last one.
        $last = (string) array_pop($segments);
        $last = (string) preg_replace('/(\.[^.]+)+$/', '', $last);
        $segments[] = $last;

        // Drop characters invalid in a TypeScript identifier (e.g. '@' in '@js'), but keep
        // hyphens and underscores — Str::studly treats them as word separators.
        $segments = array_values(array_filter(
            array_map(fn (string $s) => (string) preg_replace('/[^A-Za-z0-9_-]/', '', $s), $segments),
            fn (string $s) => $s !== ''
        ));

        if ($segments === []) {
            return 'Unknown'; // @codeCoverageIgnore
        }

        return Str::studly(implode(' ', $segments));
    }
}
