<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use Illuminate\Support\Facades\Config;

/**
 * Turns one MethodAnalysis into an AnalysisResult whose three fields agree with each other.
 *
 * AnalysisImports resolves *what* to import from the raw channels and stops there, which leaves a
 * caller holding types the imports do not match: an `EnumResource::make()` property still spelled
 * `XType`, two same-basename models both spelled by their bare name. This runs the remaining
 * rewrites over the properties themselves — collision aliases first, then the AsEnum wrap — and
 * then imports exactly the tokens the rewritten types spell.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ResourcePropertyInfoList from AnalysisResult
 *
 * @phpstan-type ComposedProperty = array{type: string, optional: bool, description: string}
 * @phpstan-type EnumResourceArm = array{fqcn: class-string, nullable: bool, wrapIsCollection: bool, directIsArray: bool}
 *
 * @internal
 */
final class AnalysisComposer
{
    use BuildsImportMaps;
    use ResolvesImportConflicts;

    /** @var array<string, ComposedProperty> property name => the property being rewritten */
    private array $properties = [];

    /** @var array<string, string> FQCN => bare TypeScript enum type name */
    private array $enumFqcnMap = [];

    /** @var array<string, string> FQCN => TypeScript const name; formatConstImportName() reads it. */
    protected array $enumConstMap = [];

    /** @var array<string, string> FQCN => resource type name */
    private array $resourceFqcnMap = [];

    /** @var array<string, string> FQCN => model type name */
    private array $modelFqcnMap = [];

    /** @var array<string, list<class-string>> property name => positional FQCN queue for aliasing */
    private array $propertyFqcnQueues = [];

    /** The importing file's namespace path; BuildsImportMaps computes relative paths from it. */
    protected string $namespacePath = '';

    /**
     * Compose one analysis into properties, type imports and value imports that agree.
     *
     * $fromNamespacePath is the importing file's namespace path (e.g. 'workbench/app/events').
     */
    public function compose(MethodAnalysis $analysis, string $fromNamespacePath): AnalysisResult
    {
        $this->namespacePath = $fromNamespacePath;

        $this->collectProperties($analysis);
        $this->collectNameMaps($analysis);
        $this->propertyFqcnQueues = $this->buildPropertyFqcnQueues($analysis);

        $this->resolveImportConflicts();
        $this->rewriteEnumResourceTypes($analysis);

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

        foreach ($this->enumTypeFqcns($analysis) as $fqcn) {
            $tsInfo = LaravelTsPublish::toTsType($fqcn);
            $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
            $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
        }

        foreach ($this->inlineEnumResourceFqcns($analysis) as $fqcn) {
            $this->enumConstMap[$fqcn] ??= LaravelTsPublish::toTsType($fqcn)['enums'][0] ?? class_basename($fqcn);
        }

        foreach ($analysis->nestedResources as $fqcn) {
            $this->resourceFqcnMap[$fqcn] = LaravelTsPublish::resourceTypeName($fqcn);
        }

        foreach ($analysis->modelFqcns as $fqcn) {
            $this->modelFqcnMap[$fqcn] = class_basename($fqcn);
        }
    }

    /**
     * Assign collision-free local names across every FQCN map, then rewrite the types that use them.
     *
     * Two sibling registries, exactly as ResourceTransformer runs them: a const name equal to another
     * enum's type name still collides, which is that class's own documented limitation.
     */
    private function resolveImportConflicts(): void
    {
        $this->importAliases = [];
        $this->constImportAliases = [];

        $skip = ['Models', 'Enums', 'Http', 'Resources', 'App'];

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

        $this->applyResolvedImportNames(
            $registry->resolve(),
            $this->enumFqcnMap + $this->resourceFqcnMap + $this->modelFqcnMap,
            $constRegistry->resolve(),
        );
    }

    /**
     * Rewrite every EnumResource-wrapped property to `AsEnum<typeof Const>`, the shape the tolki
     * package publishes. With the package off the bare enum type name is already the answer.
     */
    private function rewriteEnumResourceTypes(MethodAnalysis $analysis): void
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return;
        }

        foreach ($this->enumResourceArms($analysis) as $propName => $arm) {
            $constName = $this->constImportAliases[$arm['fqcn']] ?? $this->enumConstMap[$arm['fqcn']];
            $typeName = $this->importAliases[$arm['fqcn']] ?? $this->enumFqcnMap[$arm['fqcn']];

            if (isset($analysis->directEnumFqcns[$propName])) {
                // A mixed ternary collapsed both arms to one deduped token, so substitution cannot
                // tell them apart — synthesize the union from each arm's own recorded shape.
                $type = 'AsEnum<typeof '.$constName.'>'.($arm['wrapIsCollection'] ? '[]' : '')
                    .' | '.$typeName.($arm['directIsArray'] ? '[]' : '')
                    .($arm['nullable'] ? ' | null' : '');
            } else {
                $type = TsTypeString::substituteEnumType(
                    $this->properties[$propName]['type'],
                    $typeName,
                    'AsEnum<typeof '.$constName.'>',
                );
            }

            $this->properties[$propName]['type'] = $type;
        }

        foreach ($analysis->multiEnumResourceFqcns as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue;
            }

            $this->properties[$propName]['type'] = $this->rewriteMultiEnumUnion(
                $this->properties[$propName]['type'],
                $fqcns,
            );
        }

        // analyzeInlineArray() already wrote `AsEnum<typeof {bare}>` into the inline object type, and
        // rewriteTypeReferences() cannot alias it — its name map holds type names, never const ones.
        foreach ($analysis->inlineEnumResourceFqcns as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue;
            }

            $this->properties[$propName]['type'] = TsTypeString::aliasPropertyType(
                $this->properties[$propName]['type'],
                $fqcns,
                $this->enumConstMap,
                $this->constImportAliases,
            );
        }
    }

    /**
     * Replace each non-null arm of a ternary whose branches wrap different enums, in branch order.
     *
     * @param  list<class-string>  $fqcns
     */
    private function rewriteMultiEnumUnion(string $type, array $fqcns): string
    {
        $rewritten = [];
        $index = 0;

        foreach (TsTypeString::splitTopLevelUnion($type) as $token) {
            if ($token === 'null' || ! isset($fqcns[$index])) {
                $rewritten[] = $token;

                continue;
            }

            $fqcn = $fqcns[$index++];
            $rewritten[] = 'AsEnum<typeof '.($this->constImportAliases[$fqcn] ?? $this->enumConstMap[$fqcn]).'>';
        }

        return implode(' | ', $rewritten);
    }

    /**
     * Each EnumResource property's arm shape, for the rewrite above.
     *
     * @return array<string, EnumResourceArm>
     */
    private function enumResourceArms(MethodAnalysis $analysis): array
    {
        $arms = [];

        foreach ($analysis->enumResources as $propName => $fqcn) {
            if (! isset($this->properties[$propName], $this->enumFqcnMap[$fqcn])) {
                continue;
            }

            $type = $this->properties[$propName]['type'];
            // $type may already carry '| null', so the collection check must strip it first.
            $isCollection = str_ends_with(rtrim(str_replace('| null', '', $type)), '[]');
            $armShape = $analysis->enumResourceArmShapes[$propName] ?? null;

            $arms[$propName] = [
                'fqcn' => $fqcn,
                'nullable' => str_contains($type, 'null'),
                'wrapIsCollection' => $armShape['wrapIsCollection'] ?? false,
                'directIsArray' => $armShape['directIsArray'] ?? $isCollection,
            ];
        }

        return $arms;
    }

    /**
     * Build the type imports, keeping only the names the rewritten properties actually spell.
     *
     * An enum the AsEnum wrap replaced, and one a `#[TsCasts]` override displaced, both leave a
     * live FQCN channel behind; importing either is a dead `import type` under noUnusedLocals.
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

        $typeImports = $this->mergeCustomImports($typeImports, $analysis->customImports);

        return $this->deduplicateAndSortImports($this->pruneUnspelledImports($typeImports));
    }

    /**
     * Build the const import map the tolki package's AsEnum wrapper consumes.
     *
     * @return TypesImportMap
     */
    private function buildValueImports(MethodAnalysis $analysis): array
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return [];
        }

        $fqcns = [
            ...array_values($analysis->enumResources),
            ...$this->inlineEnumResourceFqcns($analysis),
        ];

        foreach ($analysis->multiEnumResourceFqcns as $branchFqcns) {
            $fqcns = [...$fqcns, ...$branchFqcns];
        }

        $valueImports = $this->collectModularValueImports(array_values(array_unique($fqcns)));

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
        $rendered = implode("\n", array_column($this->properties, 'type'));

        foreach ($imports as $path => $names) {
            $kept = array_values(array_filter(
                $names,
                fn (string $name): bool => TsTypeString::typeNameOccursIn($this->localName($name), $rendered),
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
     * The local name an import specifier binds — the alias when it carries `Original as Alias`.
     */
    private function localName(string $specifier): string
    {
        $position = strpos($specifier, ' as ');

        return $position === false ? $specifier : substr($specifier, $position + 4);
    }

    /**
     * Merge every per-property FQCN map into one prefix-aligned queue per property.
     *
     * A property in more than one map gets each map's entries concatenated, so the queue can be
     * longer than the type's real occurrences; aliasPropertyType() consumes only the matching
     * prefix, which is also why a repeat must survive as a repeat.
     *
     * @return array<string, list<class-string>>
     */
    private function buildPropertyFqcnQueues(MethodAnalysis $analysis): array
    {
        /** @var array<string, list<class-string>> $queues */
        $queues = [];

        foreach ([
            $analysis->enumResources,
            $analysis->directEnumFqcns,
            $analysis->nestedResources,
            $analysis->modelFqcns,
        ] as $map) {
            foreach ($map as $propName => $fqcn) {
                $queues[$propName][] = $fqcn;
            }
        }

        foreach ([$analysis->inlineEnumFqcns, $analysis->inlineModelFqcns] as $map) {
            foreach ($map as $propName => $fqcns) {
                $queues[$propName] = [...($queues[$propName] ?? []), ...$fqcns];
            }
        }

        return $queues;
    }

    /**
     * Enum FQCNs that need a bare TypeScript type name.
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
     * Enum FQCNs reachable only through an inline object type, which need a const name but no type.
     *
     * @return list<class-string>
     */
    private function inlineEnumResourceFqcns(MethodAnalysis $analysis): array
    {
        $fqcns = [];

        foreach ($analysis->inlineEnumResourceFqcns as $branchFqcns) {
            $fqcns = [...$fqcns, ...$branchFqcns];
        }

        return array_values(array_unique($fqcns));
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
