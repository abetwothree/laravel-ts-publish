<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Parses #[TsExtends] attributes and config entries into merged extends clauses and imports, and a resource's
 * #[TsIntersect] attributes into its intersected types beside them.
 *
 * A type name reachable from two different import paths, across every clause settled together, is aliased with that
 * path's last segment (`Routable` from `@/types/routing` → `RoutingRoutable`), and its clause is rewritten.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type ClauseAttribute = class-string<TsExtends|TsIntersect>
 * @phpstan-type ConfigScope = 'broadcast_events'|'form_requests'|'models'|'resources'
 * @phpstan-type ClauseEntry = array{
 *     attribute: ClauseAttribute,
 *     extends: string,
 *     import: string|null,
 *     types: list<string>,
 *     listed: list<string>,
 * }
 * @phpstan-type TsExtendsResult = array{extends: list<string>, imports: array<string, list<string>>}
 * @phpstan-type ClauseSets = array{clauses: array<ClauseAttribute, list<string>>, imports: array<string, list<string>>}
 */
trait ParsesTsExtends
{
    /**
     * Parse #[TsExtends] attributes and config entries for the given scope.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  ConfigScope  $scope
     * @return TsExtendsResult
     */
    protected function parseTsExtendsFromReflection(ReflectionClass $reflection, string $scope): array
    {
        $result = $this->settleClauses($this->readClauses($reflection, [TsExtends::class => $scope]));

        return ['extends' => $result['clauses'][TsExtends::class], 'imports' => $result['imports']];
    }

    /**
     * Read clause attributes from a class, its traits and its parents, breadth-first, each followed by the config
     * entries of its scope, if it has one.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  array<ClauseAttribute, ConfigScope|null>  $attributes  each attribute => its config scope
     * @return list<ClauseEntry>
     */
    protected function readClauses(ReflectionClass $reflection, array $attributes): array
    {
        $entries = [];
        $classes = $this->clauseClasses($reflection);

        foreach ($attributes as $attribute => $configScope) {
            foreach ($classes as $class) {
                foreach ($class->getAttributes($attribute) as $attr) {
                    $instance = $attr->newInstance();
                    $clause = $instance instanceof TsIntersect ? $instance->type : $instance->extends;
                    $entries[] = $this->clauseEntry($attribute, $clause, $instance->import, $instance->types);
                }
            }

            /** @var list<string|array{extends: string, import?: string, types?: list<string>}> $configEntries */
            $configEntries = $configScope === null ? [] : array_values(array_filter(
                Config::array("ts-publish.ts_extends.{$configScope}", []),
                fn (mixed $v): bool => is_string($v) || is_array($v),
            ));

            foreach ($configEntries as $entry) {
                $entry = is_string($entry) ? ['extends' => $entry] : $entry;
                $import = $entry['import'] ?? null;
                $entries[] = $this->clauseEntry($attribute, $entry['extends'], $import, $entry['types'] ?? null);
            }
        }

        return $entries;
    }

    /**
     * Deduplicate the clauses and alias each name two import paths bring, leaving out each name a clause only builds on
     * that the file binds by other means: another import, or the package's own import of it. A name the clause lists,
     * its `types` or a top-level arm, stays the clause's, aliased apart from another path's import of it.
     *
     * @param  list<ClauseEntry>  $entries
     * @param  TypesImportMap  $imports  the file's other imports, path => names
     * @param  list<string>  $packageNames  the names the package's own imports bind in the file
     * @return ClauseSets
     */
    protected function settleClauses(array $entries, array $imports = [], array $packageNames = []): array
    {
        $paths = [];

        foreach ($imports as $path => $names) {
            foreach ($names as $name) {
                $paths[ImportMaps::localName($name)][] = $path;
            }
        }

        $unbound = [];

        foreach ($entries as $entry) {
            $unbound[] = [...$entry, 'types' => array_values(array_filter(
                $entry['types'],
                static fn (string $name): bool => in_array($name, $entry['listed'], true)
                    ? ! in_array($entry['import'], $paths[$name] ?? [], true)
                    : ! isset($paths[$name]) && ! in_array($name, $packageNames, true),
            ))];
        }

        return $this->deduplicateAndResolveExtendsConflicts($unbound, array_keys($paths));
    }

    /**
     * The names a clause with an import and no `types` imports: its top-level arms, as extractImportableTypes() reads
     * them, unless its publisher reads more.
     *
     * @return list<string>
     */
    protected function clauseTypeNames(string $clause): array
    {
        return TsTypeString::extractImportableTypes($clause);
    }

    /**
     * The names a clause lists whole: each top-level arm that is one name, such as `Meta` or `Meta[]`.
     *
     * @return list<string>
     */
    protected function listedTypeNames(string $clause): array
    {
        $names = [];

        // An arm holding a `|` holds a nested union, so it is no name whole; extractImportableTypes() reads each other.
        foreach (TsTypeString::splitTopLevelUnion($clause) as $arm) {
            if (! str_contains($arm, '|')) {
                array_push($names, ...TsTypeString::extractImportableTypes($arm));
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The class, then its traits and parent classes, breadth-first, each once: where its clause attributes are read.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @return list<ReflectionClass<object>>
     */
    private function clauseClasses(ReflectionClass $reflection): array
    {
        $classes = [$reflection];
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
            $classes[] = $current;

            foreach ($current->getTraits() as $trait) {
                $queue[] = $trait;
            }
            if ($parent = $current->getParentClass()) {
                $queue[] = $parent;
            }
        }

        return $classes;
    }

    /**
     * One clause as read. With an import it brings its `types`, or else the names clauseTypeNames() reads, and it
     * lists its `types`, or else the names listedTypeNames() reads.
     *
     * @param  ClauseAttribute  $attribute
     * @param  list<string>|null  $types
     * @return ClauseEntry
     */
    private function clauseEntry(string $attribute, string $clause, ?string $import, ?array $types): array
    {
        return [
            'attribute' => $attribute,
            'extends' => $clause,
            'import' => $import,
            'types' => $import === null ? [] : ($types ?? $this->clauseTypeNames($clause)),
            'listed' => $import === null ? [] : ($types ?? $this->listedTypeNames($clause)),
        ];
    }

    /**
     * Deduplicate entries and resolve type name conflicts across import paths.
     *
     * @param  list<ClauseEntry>  $rawEntries
     * @param  list<string>  $taken  the names another import of the file binds, which a clause's import of them aliases
     * @return ClauseSets
     */
    private function deduplicateAndResolveExtendsConflicts(array $rawEntries, array $taken = []): array
    {
        $deduped = [];
        $seenPairs = [];

        foreach ($rawEntries as $entry) {
            $key = $entry['attribute']."\0".$entry['extends']."\0".($entry['import'] ?? '');
            if (! isset($seenPairs[$key])) {
                $seenPairs[$key] = true;
                $deduped[] = $entry;
            }
        }

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

        // Only names reachable from more than one import path need an alias.
        /** @var array<string, string> $aliasMap */
        $aliasMap = [];
        foreach ($typeToImportPaths as $typeName => $importPaths) {
            if (count($importPaths) <= 1 && ! in_array($typeName, $taken, true)) {
                continue;
            }
            foreach ($importPaths as $importPath) {
                $prefix = Str::studly(basename($importPath));
                $aliasMap[$typeName."\0".$importPath] = $prefix.$typeName;
            }
        }

        $clauses = array_fill_keys([TsExtends::class, TsIntersect::class], []);
        /** @var array<string, list<string>> $imports */
        $imports = [];

        foreach ($deduped as $entry) {
            $extendsClause = $entry['extends'];

            if ($entry['import'] !== null) {
                $typeNamesToImport = [];
                $aliases = [];
                foreach ($entry['types'] as $typeName) {
                    $aliasKey = $typeName."\0".$entry['import'];
                    if (isset($aliasMap[$aliasKey])) {
                        $aliases[$typeName] = $aliasMap[$aliasKey];
                        $typeNamesToImport[] = $typeName.' as '.$aliasMap[$aliasKey];
                    } else {
                        $typeNamesToImport[] = $typeName;
                    }
                }

                $extendsClause = TsTypeString::aliasReferences($extendsClause, $aliases);

                $existing = $imports[$entry['import']] ?? [];
                foreach ($typeNamesToImport as $tn) {
                    if (! in_array($tn, $existing, true)) {
                        $existing[] = $tn;
                    }
                }

                if ($existing !== []) {
                    $imports[$entry['import']] = $existing;
                }
            }

            $clauses[$entry['attribute']][] = $extendsClause;
        }

        return ['clauses' => $clauses, 'imports' => $imports];
    }
}
