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

            $prefix = $this->computePathPrefix($path);
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
     * Derive a StudlyCase prefix from the last path segment.
     *
     * For example, `@js/types/user-profile` → `UserProfile`.
     */
    private function computePathPrefix(string $path): string
    {
        $segment = basename($path);

        // Strip any file extension (e.g. .ts, .js, .d.ts)
        $segment = (string) preg_replace('/\.[^.]+$/', '', $segment);

        return Str::studly($segment);
    }
}
