<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
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
     * Deduplicate the clauses and alias each name two import paths bring, leaving out each name the file binds by other
     * means: one another import brings, or one a package class takes that the clause only builds on. A name the clause
     * lists, its `types` or a top-level arm, stays the clause's, and that class is aliased apart.
     *
     * @param  list<ClauseEntry>  $entries
     * @param  list<string>  $imported  the local names the file's other imports bind
     * @param  list<string>  $classes  the names the classes of the package the file imports take
     * @return ClauseSets
     */
    protected function settleClauses(array $entries, array $imported = [], array $classes = []): array
    {
        $unbound = [];

        foreach ($entries as $entry) {
            $unbound[] = [...$entry, 'types' => array_values(array_filter(
                $entry['types'],
                static fn (string $name): bool => ! in_array($name, $imported, true)
                    && (in_array($name, $entry['listed'], true) || ! in_array($name, $classes, true)),
            ))];
        }

        return $this->deduplicateAndResolveExtendsConflicts($unbound);
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
     * lists its `types`, or else its top-level arms, the names it brought before any clause read more.
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
            'listed' => $import === null ? [] : ($types ?? TsTypeString::extractImportableTypes($clause)),
        ];
    }

    /**
     * Deduplicate entries and resolve type name conflicts across import paths.
     *
     * @param  list<ClauseEntry>  $rawEntries
     * @return ClauseSets
     */
    private function deduplicateAndResolveExtendsConflicts(array $rawEntries): array
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
            if (count($importPaths) <= 1) {
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
