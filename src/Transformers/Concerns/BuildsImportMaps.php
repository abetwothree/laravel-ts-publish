<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers\Concerns;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;

/**
 * Shared helpers for building TypeScript import maps in both transformers.
 *
 * @phpstan-import-type TypesImportMap from \AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable
 */
trait BuildsImportMaps
{
    /**
     * Collect modular (per-file) type imports for a set of FQCNs.
     *
     * @param  array<string, string>  $fqcnMap  FQCN => TypeScript type name
     * @return TypesImportMap
     */
    protected function collectModularTypeImports(array $fqcnMap): array
    {
        $imports = [];

        foreach ($fqcnMap as $fqcn => $typeName) {
            $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
            $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
            $imports[$importPath][] = $this->formatImportName($fqcn, $typeName);
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
            $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
            $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
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

        return LaravelTsPublish::sortImportPaths($imports);
    }

    /**
     * Collect flat (barrel) type imports into a single path bucket.
     *
     * @param  array<string, string>  $fqcnMap  FQCN => TypeScript type name
     * @return list<string>
     */
    protected function collectFlatTypeImports(array $fqcnMap): array
    {
        $imports = [];

        foreach ($fqcnMap as $fqcn => $typeName) {
            $imports[] = $this->formatImportName($fqcn, $typeName);
        }

        return array_values(array_unique($imports));
    }

    /**
     * Collect flat (barrel) const imports for a set of FQCNs.
     *
     * @param  list<string>  $fqcns  List of enum FQCNs to import
     * @return list<string>
     */
    protected function collectFlatValueImports(array $fqcns): array
    {
        $imports = [];

        foreach ($fqcns as $fqcn) {
            $imports[] = $this->formatConstImportName($fqcn);
        }

        return array_values(array_unique($imports));
    }

    /**
     * Build flat enum type and value imports for the '../enums' import path.
     *
     * @param  array<string, string>  $enumFqcnMap  FQCN => TypeScript type name
     * @param  list<string>  $enumFqcns  Ordered list of enum FQCNs for value imports
     * @return array{typeImports: TypesImportMap, valueImports: TypesImportMap}
     */
    protected function buildFlatEnumImports(array $enumFqcnMap, array $enumFqcns, bool $hasEnums): array
    {
        $typeImports = [];
        $valueImports = [];

        $this->addSortedImports($typeImports, '../enums', $this->collectFlatTypeImports($enumFqcnMap));

        if ($hasEnums) {
            $this->addSortedImports($valueImports, '../enums', $this->collectFlatValueImports($enumFqcns));
        }

        return ['typeImports' => $typeImports, 'valueImports' => $valueImports];
    }

    /**
     * Sort $items and add to $imports under $path if non-empty.
     *
     * @param  TypesImportMap  $imports
     * @param  list<string>  $items
     */
    protected function addSortedImports(array &$imports, string $path, array $items): void
    {
        if ($items !== []) {
            sort($items);
            $imports[$path] = $items;
        }
    }
}
