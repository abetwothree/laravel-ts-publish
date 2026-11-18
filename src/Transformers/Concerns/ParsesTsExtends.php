<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Parses #[TsExtends] attributes and config entries into merged extends clauses and imports, and a resource's
 * #[TsIntersect] attributes into its intersected types in the same pass.
 *
 * A type name reachable from two different import paths, across every attribute parsed together, is aliased with that
 * path's last segment (`Routable` from `@/types/routing` → `RoutingRoutable`), and its clause is rewritten.
 *
 * @phpstan-type ClauseAttribute = class-string<TsExtends|TsIntersect>
 * @phpstan-type ConfigScope = 'broadcast_events'|'form_requests'|'models'|'resources'
 * @phpstan-type RawEntry = array{
 *     attribute: ClauseAttribute,
 *     extends: string,
 *     import: string|null,
 *     types: list<string>,
 *     read: bool,
 * }
 * @phpstan-type TsExtendsResult = array{
 *     extends: list<string>,
 *     imports: array<string, list<string>>,
 *     nested: array<string, list<string>>,
 * }
 * @phpstan-type ClauseSets = array{
 *     clauses: array<ClauseAttribute, list<string>>,
 *     imports: array<string, list<string>>,
 *     nested: array<string, list<string>>,
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
     * @param  ConfigScope  $scope
     * @return TsExtendsResult
     */
    protected function parseTsExtendsFromReflection(ReflectionClass $reflection, string $scope): array
    {
        $result = $this->parseClauseAttributes($reflection, [TsExtends::class => $scope]);

        return ['extends' => $result['clauses'][TsExtends::class], 'imports' => $result['imports'], 'nested' => $result['nested']];
    }

    /**
     * Parse clause attributes from a class, its traits and its parents, breadth-first, each followed by the config
     * entries of its scope, if it has one, in one pass: a name two of them import from two paths is aliased apart.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  array<ClauseAttribute, ConfigScope|null>  $attributes  each attribute => its config scope
     * @return ClauseSets
     */
    protected function parseClauseAttributes(ReflectionClass $reflection, array $attributes): array
    {
        /** @var list<RawEntry> $rawEntries */
        $rawEntries = [];
        $classes = $this->clauseClasses($reflection);

        foreach ($attributes as $attribute => $configScope) {
            foreach ($classes as $class) {
                $this->collectClauseAttributes($class, $attribute, $rawEntries);
            }

            /** @var list<string|array{extends: string, import?: string, types?: list<string>}> $configEntries */
            $configEntries = $configScope === null ? [] : array_values(array_filter(
                Config::array("ts-publish.ts_extends.{$configScope}", []),
                fn (mixed $v): bool => is_string($v) || is_array($v),
            ));

            foreach ($configEntries as $entry) {
                if (is_string($entry)) {
                    $rawEntries[] = ['attribute' => $attribute, 'extends' => $entry, 'import' => null, 'types' => [], 'read' => false];
                } else {
                    /** @var array{extends: string, import?: string, types?: list<string>} $entry */
                    $typeNames = isset($entry['import'])
                        ? ($entry['types'] ?? $this->clauseTypeNames($entry['extends']))
                        : [];
                    $rawEntries[] = [
                        'attribute' => $attribute,
                        'extends' => $entry['extends'],
                        'import' => $entry['import'] ?? null,
                        'types' => $typeNames,
                        'read' => isset($entry['import']) && ! isset($entry['types']),
                    ];
                }
            }
        }

        return $this->deduplicateAndResolveExtendsConflicts($rawEntries, array_keys($attributes));
    }

    /**
     * The names a clause with an import and no `types` imports: its top-level arms, as extractImportableTypes() reads
     * them. A publisher that keeps the package's import for a name a clause only builds on reads every name instead.
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
     * Collect one clause attribute from a single reflection class into the entries list.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflection
     * @param  ClauseAttribute  $attribute
     * @param  list<RawEntry>  $entries
     */
    private function collectClauseAttributes(ReflectionClass $reflection, string $attribute, array &$entries): void
    {
        foreach ($reflection->getAttributes($attribute) as $attr) {
            $instance = $attr->newInstance();
            $clause = $instance instanceof TsIntersect ? $instance->type : $instance->extends;
            $typeNames = $instance->import !== null
                ? ($instance->types ?? $this->clauseTypeNames($clause))
                : [];
            $entries[] = [
                'attribute' => $attribute,
                'extends' => $clause,
                'import' => $instance->import,
                'types' => $typeNames,
                'read' => $instance->import !== null && $instance->types === null,
            ];
        }
    }

    /**
     * Deduplicate entries and resolve type name conflicts across import paths, and list apart each name read from a
     * clause that only builds on it, as `UserResource` in `Pick<UserResource, "id">`, while it keeps its spelling.
     *
     * @param  list<RawEntry>  $rawEntries
     * @param  list<ClauseAttribute>  $attributes
     * @return ClauseSets
     */
    private function deduplicateAndResolveExtendsConflicts(array $rawEntries, array $attributes): array
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

        // A name an entry only builds on takes no alias: the publisher imports it only where nothing else binds it.
        $builtOn = array_map(
            static fn (array $entry): array => $entry['read'] && $entry['import'] !== null
                ? array_values(array_diff($entry['types'], CastChannels::wholeNames($entry['extends'])))
                : [],
            $deduped,
        );

        /** @var array<string, list<string>> $typeToImportPaths */
        $typeToImportPaths = [];
        foreach ($deduped as $index => $entry) {
            if ($entry['import'] === null) {
                continue;
            }
            foreach (array_diff($entry['types'], $builtOn[$index]) as $typeName) {
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

        $clauses = array_fill_keys($attributes, []);
        /** @var array<string, list<string>> $imports */
        $imports = [];
        /** @var array<string, list<string>> $nested */
        $nested = [];
        /** @var array<string, list<string>> $claimed  the names an entry imports whole or lists in its `types` */
        $claimed = [];

        foreach ($deduped as $index => $entry) {
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

                        if (in_array($typeName, $builtOn[$index], true)) {
                            $nested[$entry['import']][] = $typeName;
                        } else {
                            $claimed[$entry['import']][] = $typeName;
                        }
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

        foreach ($nested as $path => $names) {
            $nested[$path] = array_values(array_unique(array_diff($names, $claimed[$path] ?? [])));
        }

        return ['clauses' => $clauses, 'imports' => $imports, 'nested' => array_filter($nested)];
    }
}
