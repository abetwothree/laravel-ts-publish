<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use Illuminate\Support\Str;

/**
 * Shared helpers for building TypeScript import maps in both transformers.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type SiblingImportNames from ImportNameRegistry
 */
trait BuildsImportMaps
{
    /**
     * @var SiblingImportNames each sibling name an FQCN is imported under, such as an enum's `…Kind`; declared as
     *                         ResolvesImportConflicts declares it, so a class using both holds one property
     */
    protected array $siblingImportNames = [];

    /**
     * The import name a sibling name, such as an enum's `…Kind`, takes under its local name.
     */
    abstract protected function formatSiblingImportName(string $name, string $local): string;

    /**
     * Collect modular (per-file) type imports for a set of FQCNs, each beside the sibling names it is imported under.
     *
     * With `$spelledIn`, a class imported under a sibling name keeps only the names those types spell: an enum a type
     * reads by its `…Kind` alone does not import its `…Type`.
     *
     * @param  array<string, string>  $fqcnMap  FQCN => TypeScript type name
     * @param  list<string>|null  $spelledIn  the types the file publishes, as it spells them
     * @return TypesImportMap
     */
    protected function collectModularTypeImports(array $fqcnMap, ?array $spelledIn = null): array
    {
        $imports = [];

        foreach ($fqcnMap as $fqcn => $typeName) {
            $targetPath = TsNaming::namespaceToPath($fqcn);
            $importPath = TsNaming::relativeImportPath($this->namespacePath, $targetPath);
            $names = [$this->formatImportName($fqcn, $typeName)];

            foreach ($this->siblingImportNames[$fqcn] ?? [] as $name => $local) {
                $names[] = $this->formatSiblingImportName($name, $local);
            }

            if ($spelledIn !== null && count($names) > 1) {
                $names = array_filter(
                    $names,
                    static fn (string $name): bool => TsTypeString::typeNameOccursIn(Str::afterLast($name, ' as '), ...$spelledIn),
                );
            }

            if ($names !== []) {
                $imports[$importPath] = [...($imports[$importPath] ?? []), ...array_values($names)];
            }
        }

        return $imports;
    }

    /**
     * Collect modular (per-file) const/value imports for a set of FQCNs.
     *
     * @param  list<string>  $fqcns  List of enum FQCNs to import
     * @return TypesImportMap
     */
    protected function collectModularValueImports(array $fqcns): array
    {
        $imports = [];

        foreach ($fqcns as $fqcn) {
            $targetPath = TsNaming::namespaceToPath($fqcn);
            $importPath = TsNaming::relativeImportPath($this->namespacePath, $targetPath);
            $imports[$importPath][] = $this->formatConstImportName($fqcn);
        }

        return $imports;
    }

    /**
     * Merge custom imports into a type-imports map.
     *
     * @param  TypesImportMap  $typeImports
     * @param  array<string, list<string>>  $customImports
     * @return TypesImportMap
     */
    protected function mergeCustomImports(array $typeImports, array $customImports): array
    {
        foreach ($customImports as $path => $types) {
            if ($types !== []) {
                $existing = $typeImports[$path] ?? [];
                $typeImports[$path] = array_values(array_unique([...$existing, ...$types]));
            }
        }

        return $typeImports;
    }

    /**
     * Deduplicate and sort each path's imports, then sort by path.
     *
     * @param  TypesImportMap  $imports
     * @return TypesImportMap
     */
    protected function deduplicateAndSortImports(array $imports): array
    {
        foreach ($imports as $path => $types) {
            $unique = array_values(array_unique($types));
            sort($unique);
            $imports[$path] = $unique;
        }

        return TsNaming::sortImportPaths($imports);
    }
}
