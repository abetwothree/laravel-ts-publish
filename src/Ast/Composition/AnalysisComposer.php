<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapImports;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapSpelling;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use Closure;

/**
 * Turns one MethodAnalysis into an AnalysisResult whose three fields agree with each other.
 *
 * AnalysisImports resolves *what* to import from the raw channels and stops there, which leaves a
 * caller holding types the imports do not match: two same-basename models both spelled by their bare
 * name. This aliases the names in the properties themselves, each class's and then each const a wrap's
 * `typeof` reads, and then imports exactly the tokens the rewritten types spell.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ResourcePropertyInfoList from AnalysisResult
 *
 * @phpstan-type ComposedProperty = array{type: string, optional: bool, description: string}
 *
 * @internal
 */
final class AnalysisComposer
{
    use BuildsImportMaps;
    use ResolvesImportConflicts;

    /** @var array<string, string> FQCN => TypeScript const name; formatConstImportName() reads it. */
    protected array $enumConstMap = [];

    /** The importing file's namespace path; BuildsImportMaps computes relative paths from it. */
    protected string $namespacePath = '';

    /** @var array<array-key, ComposedProperty> property name => the property being rewritten */
    private array $properties = [];

    /** @var array<string, string> FQCN => bare TypeScript enum type name */
    private array $enumFqcnMap = [];

    /** @var array<string, string> FQCN => resource type name */
    private array $resourceFqcnMap = [];

    /** @var array<string, string> FQCN => model type name */
    private array $modelFqcnMap = [];

    /** @var array<array-key, list<class-string>> property name => positional FQCN queue for aliasing */
    private array $propertyFqcnQueues = [];

    /**
     * Compose one analysis into properties, type imports and value imports that agree.
     *
     * $fromNamespacePath is the importing file's namespace path (e.g. 'workbench/app/events').
     */
    public function compose(MethodAnalysis $analysis, string $fromNamespacePath): AnalysisResult
    {
        $this->namespacePath = $fromNamespacePath;

        // No publisher lays another cast over this one, so a method's own cast is the one in force; its key counts with
        // the cast's text, as each publisher's reconcile counts it, before fit() settles its import channels.
        resolve(IndexSignatureReconciler::class)
            ->reconcile($analysis, array_map(fn (array $cast): string => $cast['type'], $analysis->casts));
        $analysis->absorbRedundantArms();
        resolve(CastChannels::class)->fit($analysis, $analysis->casts);
        $this->collectProperties($analysis);
        $this->collectNameMaps($analysis);
        $this->propertyFqcnQueues = $this->buildPropertyFqcnQueues($analysis);
        $this->registerCarried($analysis);

        $this->resolveImportConflicts($analysis->customImports);
        $this->aliasWrapConsts($analysis);

        return new AnalysisResult(
            properties: $this->composedProperties(),
            typeImports: $this->buildTypeImports($analysis),
            valueImports: $this->buildValueImports($analysis),
        );
    }

    /**
     * Rewrite every property's type to the aliased names ResolvesImportConflicts assigned.
     */
    protected function rewriteTypeReferences(): void
    {
        $nameMap = $this->enumFqcnMap + $this->resourceFqcnMap + $this->modelFqcnMap;

        foreach ($this->propertyFqcnQueues as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue;
            }

            $this->properties[$propName]['type'] = TsTypeString::aliasPropertyType(
                $this->properties[$propName]['type'],
                $fqcns,
                $nameMap,
                $this->importAliases,
                $this->siblingImportNames,
            );
        }
    }

    /**
     * Index the analysis's properties by name, so a name two branches both set is one member.
     *
     * `properties` is an append-only list, so a model spread that repeats a key it already carries
     * appears twice; rendered as-is that is a duplicate interface member.
     */
    private function collectProperties(MethodAnalysis $analysis): void
    {
        $this->properties = [];

        foreach ($analysis->properties as $property) {
            $this->properties[$property['name']] = [
                'type' => $property['type'],
                'optional' => $property['optional'],
                'description' => $property['description'],
            ];
        }
    }

    /**
     * Resolve every FQCN channel to the bare TypeScript name it would be imported under.
     */
    private function collectNameMaps(MethodAnalysis $analysis): void
    {
        $this->enumFqcnMap = [];
        $this->enumConstMap = [];
        $this->resourceFqcnMap = [];
        $this->modelFqcnMap = [];

        foreach (AnalysisImports::enumTypeFqcns($analysis) as $fqcn) {
            $this->enumFqcnMap[$fqcn] = ValueResult::enumTypeName($fqcn);
            $this->enumConstMap[$fqcn] = ValueResult::enumConstName($fqcn);
        }

        foreach ($analysis->allWrapConstFqcns() as $fqcn) {
            $this->enumConstMap[$fqcn] ??= ValueResult::enumConstName($fqcn);
        }

        foreach ($analysis->nestedResources as $fqcn) {
            $this->resourceFqcnMap[$fqcn] = TsNaming::resourceTypeName($fqcn);
        }

        foreach ($analysis->modelFqcns as $fqcn) {
            $this->modelFqcnMap[$fqcn] = class_basename($fqcn);
        }
    }

    /**
     * Register each class a cast carried that a property type spells with no class of its name behind it.
     */
    private function registerCarried(MethodAnalysis $analysis): void
    {
        $enumTypeName = ValueResult::enumTypeName(...);

        foreach ($this->carriedInUse($analysis->carried['enums'] ?? [], $this->enumFqcnMap, $enumTypeName) as $fqcn => $name) {
            $this->enumFqcnMap[$fqcn] ??= $name;
            $this->enumConstMap[$fqcn] ??= ValueResult::enumConstName($fqcn);
        }

        foreach ($this->carriedInUse($analysis->carried['models'] ?? [], $this->modelFqcnMap, class_basename(...)) as $fqcn => $name) {
            $this->modelFqcnMap[$fqcn] ??= $name;
        }

        $resourceName = static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn);

        foreach ($this->carriedInUse($analysis->carried['resources'] ?? [], $this->resourceFqcnMap, $resourceName) as $fqcn => $name) {
            $this->resourceFqcnMap[$fqcn] ??= $name;
        }
    }

    /**
     * Each carried class of one kind a property type spells with no class of its name behind it, by that name.
     *
     * @param  list<class-string>  $carried
     * @param  array<string, string>  $map  FQCN => unaliased name, of this kind's registered classes
     * @param  Closure(class-string): string  $nameOf
     * @return array<class-string, string>
     */
    private function carriedInUse(array $carried, array $map, Closure $nameOf): array
    {
        $types = array_map(static fn (array $property): string => $property['type'], $this->properties);
        $ownNames = array_map(
            static fn (array $fqcns): array => array_values(array_intersect_key($map, array_flip($fqcns))),
            $this->propertyFqcnQueues,
        );
        $inUse = [];

        foreach ($carried as $fqcn) {
            if (CastChannels::spelledUnclaimed($nameOf($fqcn), $types, $ownNames)) {
                $inUse[$fqcn] = $nameOf($fqcn);
            }
        }

        return $inUse;
    }

    /**
     * Assign collision-free local names across every FQCN map, then rewrite the types that use them.
     *
     * Two sibling registries, exactly as ResourceTransformer runs them.
     *
     * @param  array<string, list<string>>  $customImports
     */
    private function resolveImportConflicts(array $customImports): void
    {
        $this->importAliases = [];
        $this->constImportAliases = [];

        $skip = ImportNameRegistry::RESOURCE_SKIP_SEGMENTS;

        $registry = new ImportNameRegistry($skip);
        $constRegistry = new ImportNameRegistry($skip);

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
            $constRegistry->register($fqcn, $this->enumConstMap[$fqcn]);
        }

        foreach ($this->resourceFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
        }

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
        }

        foreach ($this->enumConstMap as $fqcn => $constName) {
            if (! isset($this->enumFqcnMap[$fqcn])) {
                $constRegistry->register($fqcn, $constName);
            }
        }

        $this->registerEnumKinds($registry, $this->enumFqcnMap, $this->properties, $this->propertyFqcnQueues);

        $this->applyImportNameRegistries(
            $registry,
            $constRegistry,
            $this->enumFqcnMap + $this->resourceFqcnMap + $this->modelFqcnMap,
            $customImports,
        );
    }

    /**
     * Respell each const a wrap's `AsEnum<typeof Const>` writes by the alias this file gives it.
     *
     * rewriteTypeReferences() cannot, as its name map holds type names, never const ones; the same name bare is another
     * enum's type, so it stays.
     */
    private function aliasWrapConsts(MethodAnalysis $analysis): void
    {
        if (! EnumWrapSpelling::enabled()) {
            return;
        }

        foreach ($analysis->inlineEnumResourceFqcns as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue;
            }

            $type = $this->properties[$propName]['type'];
            $this->properties[$propName]['type'] = TsTypeString::aliasTypeofConst(
                $type,
                EnumWrapImports::written($type, $fqcns, $this->constImportAliases),
                $this->enumConstMap,
                $this->constImportAliases,
            );
        }
    }

    /**
     * Build the type imports, keeping only the names the rewritten properties actually spell.
     *
     * An enum a `#[TsCasts]` override displaced leaves a live FQCN channel behind; importing it is a dead
     * `import type` under noUnusedLocals.
     *
     * @return TypesImportMap
     */
    private function buildTypeImports(MethodAnalysis $analysis): array
    {
        $typeImports = [];

        // Deliberately diverges from ResourceTransformer::buildResolvedImports(), which spreads these
        // three maps and so drops a whole path's names when two of them resolve to the same path.
        foreach ([$this->enumFqcnMap, $this->resourceFqcnMap, $this->modelFqcnMap] as $fqcnMap) {
            foreach ($this->collectModularTypeImports($fqcnMap) as $path => $names) {
                $typeImports[$path] = [...($typeImports[$path] ?? []), ...$names];
            }
        }

        $typeImports = ComposedClassTypes::resolveImports($analysis->customImports, $this->namespacePath, $typeImports);

        return $this->deduplicateAndSortImports($this->pruneUnspelledImports($typeImports));
    }

    /**
     * Build the const import map the tolki package's AsEnum wrapper consumes.
     *
     * @return TypesImportMap
     */
    private function buildValueImports(MethodAnalysis $analysis): array
    {
        if (! EnumWrapSpelling::enabled()) {
            return [];
        }

        $valueImports = $this->collectModularValueImports(EnumWrapImports::writtenIn(
            array_map(static fn (array $property): string => $property['type'], $this->properties),
            $analysis->inlineEnumResourceFqcns,
            $this->constImportAliases,
        ));

        return $this->deduplicateAndSortImports($this->pruneUnspelledImports($valueImports));
    }

    /**
     * Drop every imported name no property type names, so the result never carries a dead import.
     *
     * @param  TypesImportMap  $imports
     * @return TypesImportMap
     */
    private function pruneUnspelledImports(array $imports): array
    {
        $types = array_column($this->properties, 'type');

        foreach ($imports as $path => $names) {
            $kept = array_values(array_filter(
                $names,
                fn (string $name): bool => TsTypeString::typeNameOccursIn(ImportMaps::localName($name), ...$types),
            ));

            if ($kept === []) {
                unset($imports[$path]);

                continue;
            }

            $imports[$path] = $kept;
        }

        return $imports;
    }

    /**
     * Merge every per-property FQCN map into one prefix-aligned queue per property.
     *
     * A property in more than one map gets each map's entries concatenated, so the queue can be
     * longer than the type's real occurrences; aliasPropertyType() consumes only the matching
     * prefix, which is also why a repeat must survive as a repeat.
     *
     * @return array<array-key, list<class-string>>
     */
    private function buildPropertyFqcnQueues(MethodAnalysis $analysis): array
    {
        /** @var array<array-key, list<class-string>> $queues */
        $queues = [];

        foreach ([$analysis->directEnumFqcns, $analysis->nestedResources, $analysis->modelFqcns] as $map) {
            foreach ($map as $propName => $fqcn) {
                $queues[$propName][] = $fqcn;
            }
        }

        // The inline lists go models first, then resources: InlineObjectResult::sharedNames() relies on that order.
        foreach ([$analysis->inlineEnumFqcns, $analysis->inlineModelFqcns, $analysis->inlineResourceFqcns] as $map) {
            foreach ($map as $propName => $fqcns) {
                $queues[$propName] = [...($queues[$propName] ?? []), ...$fqcns];
            }
        }

        return $queues;
    }

    /**
     * Flatten the rewritten properties back into the list shape AnalysisResult carries.
     *
     * @return ResourcePropertyInfoList
     */
    private function composedProperties(): array
    {
        $properties = [];

        foreach ($this->properties as $name => $property) {
            $properties[] = ['name' => $name, ...$property];
        }

        return $properties;
    }
}
