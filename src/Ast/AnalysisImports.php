<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use Illuminate\Support\Facades\Config;

/**
 * Turns a MethodAnalysis's FQCN channels into resolved type/value import maps.
 *
 * No alias conflict resolution happens here; a caller whose file can emit two same-named tokens
 * runs Support\ImportNameRegistry over the result itself.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @internal
 */
final class AnalysisImports
{
    use BuildsImportMaps;

    /** @var array<string, string> FQCN => TypeScript const name; formatConstImportName() reads it. */
    protected array $enumConstMap = [];

    /** The importing file's namespace path; BuildsImportMaps computes relative paths from it. */
    protected string $namespacePath = '';

    /**
     * Build the type and value import maps for one analysis.
     *
     * $fromNamespacePath is the importing file's namespace path (e.g. 'workbench/app/events').
     *
     * @return array{typeImports: TypesImportMap, valueImports: TypesImportMap}
     */
    public function build(MethodAnalysis $analysis, string $fromNamespacePath): array
    {
        $this->namespacePath = $fromNamespacePath;
        $this->enumConstMap = [];

        $enumFqcnMap = [];

        foreach ($this->enumTypeFqcns($analysis) as $fqcn) {
            $tsInfo = LaravelTsPublish::toTsType($fqcn);
            $enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
            $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
        }

        foreach ($this->constOnlyEnumFqcns($analysis) as $fqcn) {
            $this->enumConstMap[$fqcn] ??= LaravelTsPublish::toTsType($fqcn)['enums'][0] ?? class_basename($fqcn);
        }

        foreach ($this->asEnumWrappedOnlyFqcns($analysis) as $fqcn) {
            unset($enumFqcnMap[$fqcn]);
        }

        $resourceFqcnMap = [];

        foreach ($analysis->nestedResources as $fqcn) {
            $resourceFqcnMap[$fqcn] = LaravelTsPublish::resourceTypeName($fqcn);
        }

        $modelFqcnMap = [];

        foreach ($analysis->modelFqcns as $fqcn) {
            $modelFqcnMap[$fqcn] = class_basename($fqcn);
        }

        $typeImports = [];

        // Deliberately diverges from ResourceTransformer::buildResolvedImports(), which spreads these
        // three maps and so drops a whole path's names when two of them resolve to the same path —
        // reachable for any app that keeps enums, models and resources in one namespace.
        foreach ([$enumFqcnMap, $resourceFqcnMap, $modelFqcnMap] as $fqcnMap) {
            foreach ($this->collectModularTypeImports($fqcnMap) as $path => $names) {
                $typeImports[$path] = [...($typeImports[$path] ?? []), ...$names];
            }
        }

        $typeImports = $this->mergeCustomImports($typeImports, $analysis->customImports);

        return [
            'typeImports' => $this->deduplicateAndSortImports($typeImports),
            'valueImports' => $this->deduplicateAndSortImports($this->buildValueImports($analysis)),
        ];
    }

    /**
     * Emit the type name unchanged — collision aliasing is the caller's concern.
     */
    protected function formatImportName(string $fqcn, string $typeName): string
    {
        return $typeName;
    }

    /**
     * Emit the enum's const name unchanged — collision aliasing is the caller's concern.
     */
    protected function formatConstImportName(string $fqcn): string
    {
        return $this->enumConstMap[$fqcn] ?? class_basename($fqcn);
    }

    /**
     * Enum FQCNs that need a TypeScript type import.
     *
     * @return list<class-string>
     */
    private function enumTypeFqcns(MethodAnalysis $analysis): array
    {
        $fqcns = [...array_values($analysis->enumResources), ...array_values($analysis->directEnumFqcns)];

        foreach ($analysis->multiEnumResourceFqcns as $branchFqcns) {
            $fqcns = [...$fqcns, ...$branchFqcns];
        }

        return array_values(array_unique($fqcns));
    }

    /**
     * Enum FQCNs reachable only through an inline object type, which need the const name but no type import.
     *
     * @return list<class-string>
     */
    private function constOnlyEnumFqcns(MethodAnalysis $analysis): array
    {
        $fqcns = [];

        foreach ($analysis->inlineEnumResourceFqcns as $branchFqcns) {
            $fqcns = [...$fqcns, ...$branchFqcns];
        }

        return array_values(array_unique($fqcns));
    }

    /**
     * Enums the AsEnum rewrite leaves with no bare `XType` token anywhere, so their type import is dead.
     *
     * Mirrors ResourceTransformer::rewriteEnumResourceTypes()'s GC; without it a wrapped-only enum
     * emits an unused `import type` that fails a consumer's noUnusedLocals.
     *
     * @return list<class-string>
     */
    private function asEnumWrappedOnlyFqcns(MethodAnalysis $analysis): array
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return [];
        }

        $directFqcns = array_values($analysis->directEnumFqcns);
        $inlineFqcns = [];

        foreach ($analysis->inlineEnumFqcns as $branchFqcns) {
            $inlineFqcns = [...$inlineFqcns, ...$branchFqcns];
        }

        $dead = [];

        foreach ($analysis->enumResources as $propName => $fqcn) {
            if (! isset($analysis->directEnumFqcns[$propName])
                && ! in_array($fqcn, $directFqcns, true)
                && ! in_array($fqcn, $inlineFqcns, true)) {
                $dead[] = $fqcn;
            }
        }

        // The multi-enum ternary rewrite replaces every branch token, and unlike the wrap above it
        // does not spare an enum that only an inline object type reads.
        foreach ($analysis->multiEnumResourceFqcns as $branchFqcns) {
            foreach ($branchFqcns as $fqcn) {
                if (! in_array($fqcn, $directFqcns, true)) {
                    $dead[] = $fqcn;
                }
            }
        }

        return array_values(array_unique($dead));
    }

    /**
     * Build the const/value import map, which only the tolki package's AsEnum wrapper consumes.
     *
     * @return TypesImportMap
     */
    private function buildValueImports(MethodAnalysis $analysis): array
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return [];
        }

        $fqcns = [...array_values($analysis->enumResources), ...$this->constOnlyEnumFqcns($analysis)];

        foreach ($analysis->multiEnumResourceFqcns as $branchFqcns) {
            $fqcns = [...$fqcns, ...$branchFqcns];
        }

        return $this->collectModularValueImports(array_values(array_unique($fqcns)));
    }
}
