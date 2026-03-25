<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Parses #[TsExtends] attributes from a class and global config entries,
 * returning the merged extends clauses and their associated imports.
 *
 * Deduplication and conflict resolution rules:
 *  - Identical (extends, import) pairs from any source → kept once.
 *  - Same type name, same import path (across different extends clauses) → single import statement.
 *  - Same type name, different import paths → aliased using the import path's last segment as a
 *    prefix (e.g. `Routable` from `@/types/routing` → `RoutingRoutable`) and the extends clause
 *    is rewritten to use the alias.
 *
 * @phpstan-type RawEntry = array{extends: string, import: string|null, types: list<string>}
 * @phpstan-type TsExtendsResult = array{
 *     extends: list<string>,
 *     imports: array<string, list<string>>,
 * }
 */
trait ParsesTsExtends
{
    /**
     * Parse #[TsExtends] attributes and config entries for the given scope.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  'models'|'resources'  $scope
     * @return TsExtendsResult
     */
    protected function parseTsExtendsFromReflection(ReflectionClass $reflection, string $scope): array
    {
        /** @var list<RawEntry> $rawEntries */
        $rawEntries = [];

        // Attribute-level extends on the class itself
        $this->collectTsExtendsAttributes($reflection, $rawEntries);

        // Inherited #[TsExtends] attributes from traits and parent classes (BFS)
        $queue = [...array_values($reflection->getTraits())];
        if ($parent = $reflection->getParentClass()) {
            $queue[] = $parent;
        }
        $visited = [$reflection->getName() => true];

        while ($current = array_shift($queue)) {
            $name = $current->getName();
            if (isset($visited[$name])) {
                continue;
            }
            $visited[$name] = true;

            $this->collectTsExtendsAttributes($current, $rawEntries);

            foreach ($current->getTraits() as $trait) {
                $queue[] = $trait;
            }
            if ($parent = $current->getParentClass()) {
                $queue[] = $parent;
            }
        }

        // Config-level extends
        /** @var list<string|array{extends: string, import?: string, types?: list<string>}> $configEntries */
        $configEntries = array_values(array_filter(
            config()->array("ts-publish.ts_extends.{$scope}", []),
            fn (mixed $v): bool => is_string($v) || is_array($v),
        ));

        foreach ($configEntries as $entry) {
            if (is_string($entry)) {
                $rawEntries[] = ['extends' => $entry, 'import' => null, 'types' => []];
            } else {
                /** @var array{extends: string, import?: string, types?: list<string>} $entry */
                $typeNames = isset($entry['import'])
                    ? ($entry['types'] ?? LaravelTsPublish::extractImportableTypes($entry['extends']))
                    : [];
                $rawEntries[] = [
                    'extends' => $entry['extends'],
                    'import' => $entry['import'] ?? null,
                    'types' => $typeNames,
                ];
            }
        }

        return $this->deduplicateAndResolveExtendsConflicts($rawEntries);
    }

    /**
     * Collect #[TsExtends] attributes from a single reflection class into the entries list.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  list<RawEntry>  $entries
     */
    private function collectTsExtendsAttributes(ReflectionClass $reflection, array &$entries): void
    {
        foreach ($reflection->getAttributes(TsExtends::class) as $attr) {
            $instance = $attr->newInstance();
            $typeNames = $instance->import !== null
                ? ($instance->types ?? LaravelTsPublish::extractImportableTypes($instance->extends))
                : [];
            $entries[] = [
                'extends' => $instance->extends,
                'import' => $instance->import,
                'types' => $typeNames,
            ];
        }
    }

    /**
     * Deduplicate entries and resolve type name conflicts across import paths.
     *
     * @param  list<RawEntry>  $rawEntries
     * @return TsExtendsResult
     */
    private function deduplicateAndResolveExtendsConflicts(array $rawEntries): array
    {
        // Step 1: Deduplicate identical (extends, import) pairs — covers situation 1 and BFS duplicates.
        $deduped = [];
        $seenPairs = [];

        foreach ($rawEntries as $entry) {
            $key = $entry['extends']."\0".($entry['import'] ?? '');
            if (! isset($seenPairs[$key])) {
                $seenPairs[$key] = true;
                $deduped[] = $entry;
            }
        }

        // Step 2: Build reverse map — typeName → unique list of import paths it appears under.
        /** @var array<string, list<string>> $typeToImportPaths */
        $typeToImportPaths = [];
        foreach ($deduped as $entry) {
            if ($entry['import'] === null) {
                continue;
            }
            foreach ($entry['types'] as $typeName) {
                if (! in_array($entry['import'], $typeToImportPaths[$typeName] ?? [], true)) {
                    $typeToImportPaths[$typeName][] = $entry['import'];
                }
            }
        }

        // Step 3: Build alias map for type names that appear under multiple import paths — situation 2/3.
        // Key: "$typeName\0$importPath" → aliased TypeScript name.
        /** @var array<string, string> $aliasMap */
        $aliasMap = [];
        foreach ($typeToImportPaths as $typeName => $importPaths) {
            if (count($importPaths) <= 1) {
                continue;
            }
            foreach ($importPaths as $importPath) {
                $prefix = Str::studly(basename($importPath));
                $aliasMap[$typeName."\0".$importPath] = $prefix.$typeName;
            }
        }

        // Step 4: Build final extends clauses and imports, applying aliases and deduplicating imports.
        $extendsClauses = [];
        /** @var array<string, list<string>> $imports */
        $imports = [];

        foreach ($deduped as $entry) {
            $extendsClause = $entry['extends'];

            if ($entry['import'] !== null) {
                $typeNamesToImport = [];
                foreach ($entry['types'] as $typeName) {
                    $aliasKey = $typeName."\0".$entry['import'];
                    if (isset($aliasMap[$aliasKey])) {
                        $alias = $aliasMap[$aliasKey];
                        $extendsClause = preg_replace(
                            '/\b'.preg_quote($typeName, '/').'\b/',
                            $alias,
                            $extendsClause,
                        ) ?? $extendsClause;
                        $typeNamesToImport[] = $typeName.' as '.$alias;
                    } else {
                        $typeNamesToImport[] = $typeName;
                    }
                }

                // Merge into imports, deduplicating per path — situation 3.
                $existing = $imports[$entry['import']] ?? [];
                foreach ($typeNamesToImport as $tn) {
                    if (! in_array($tn, $existing, true)) {
                        $existing[] = $tn;
                    }
                }
                $imports[$entry['import']] = $existing;
            }

            $extendsClauses[] = $extendsClause;
        }

        return ['extends' => $extendsClauses, 'imports' => $imports];
    }
}
