<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisImports;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ReturnLiteralReader;
use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Dtos\TsBroadcastEventDto;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ParsesTsExtends;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Override;
use ReflectionClass;

/**
 * Transforms a broadcast event class into a TsBroadcastEventDto.
 *
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type PropertyInfo from TsBroadcastEventDto
 * @phpstan-import-type PropertiesList from TsBroadcastEventDto
 * @phpstan-import-type CastMap from MethodAnalysis
 *
 * @extends CoreTransformer<ShouldBroadcast>
 */
class BroadcastEventTransformer extends CoreTransformer
{
    use ParsesTsCasts;
    use ParsesTsExtends;
    use ResolvesImportConflicts;
    use SnapshotsTransformerState;

    /** Short PHP class name, e.g. 'OrderShipped'. */
    public protected(set) string $eventName;

    /** Echo event string: '.Namespace.ClassName' by default, or the broadcastAs() return value. */
    public protected(set) string $broadcastName;

    /** Absolute path to the PHP source file. */
    public protected(set) string $filePath;

    /**
     * Payload property map: name → ['type' => 'number', 'optional' => false].
     *
     * @var PropertiesList
     */
    public protected(set) array $properties = [];

    /**
     * Model FQCNs found in properties (FQCN => short TS type name).
     *
     * @var array<class-string, string>
     */
    protected array $modelFqcnMap = [];

    /**
     * Enum FQCNs found in properties (FQCN => 'NameType').
     *
     * @var array<class-string, string>
     */
    protected array $enumFqcnMap = [];

    /**
     * Resolved type imports: import path => list of type names.
     *
     * @var TypesImportMap
     */
    public protected(set) array $typeImports = [];

    /**
     * Imports the analysis channels the enum and model maps do not cover need (nested resources,
     * #[TsType] imports): import path => list of type names.
     *
     * @var TypesImportMap
     */
    protected array $analysisImports = [];

    /**
     * The imports the analysis's own `#[TsType]` and method-level `#[TsCasts]` entries bring: path => type names.
     *
     * @var TypesImportMap
     */
    protected array $analysisCustomImports = [];

    /**
     * Per-property FQCN tracking — maps property name to list of FQCNs added by that property's type.
     *
     * @var array<string, list<class-string>>
     */
    protected array $propertyFqcns = [];

    /**
     * Always empty — only present because ResolvesImportConflicts::formatConstImportName() reads it.
     *
     * @var array<class-string, string>
     */
    protected array $enumConstMap = [];

    /**
     * TsCasts attribute overrides: property name => TypeScript type string.
     *
     * @var array<string, string>
     */
    protected array $tsTypeOverrides = [];

    /**
     * Import paths declared alongside TsCasts overrides: property name => import path.
     *
     * @var array<string, string>
     */
    protected array $tsCastsImportPaths = [];

    /**
     * Optional overrides from #[TsCasts]: property name => optional flag.
     *
     * @var array<string, bool>
     */
    protected array $optionalOverrides = [];

    /**
     * TypeScript extends clauses parsed from #[TsExtends] attributes and config.
     *
     * @var list<string>
     */
    public protected(set) array $tsExtends = [];

    /**
     * Import entries from TsExtends to be merged into typeImports.
     *
     * @var array<string, list<string>>
     */
    protected array $tsExtendsImports = [];

    /** @var ReflectionClass<ShouldBroadcast> */
    protected ReflectionClass $reflection;

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return ['reflection'];
    }

    #[Override]
    public function transform(): self
    {
        $this->initEventData()
            ->parseTsExtends()
            ->parseTsCasts()
            ->transformBroadcastName()
            ->transformProperties()
            ->resolveImportConflicts()
            ->buildTypeImports();

        return $this;
    }

    /**
     * Initialize core event metadata from reflection.
     */
    protected function initEventData(): self
    {
        $this->reflection = new ReflectionClass($this->findable);
        $this->eventName = $this->reflection->getShortName();
        $this->filePath = (string) $this->reflection->getFileName();
        $this->namespacePath = TsNaming::namespaceToPath($this->findable);

        return $this;
    }

    /**
     * Analyze the payload: broadcastWith() when the event has one, its public properties otherwise.
     *
     * hasMethod() on purpose, not a declared-here check: Laravel calls an inherited or trait-supplied
     * broadcastWith() too, so the payload is that method's return wherever it is defined.
     */
    protected function runAnalysis(): MethodAnalysis
    {
        $engine = resolve(AstEngine::class);

        return $this->reflection->hasMethod('broadcastWith')
            ? $engine->analyzeMethod($this->findable, 'broadcastWith')
            : $engine->analyzePublicProperties($this->findable);
    }

    /**
     * Resolve and store the Echo broadcast event string.
     */
    protected function transformBroadcastName(): self
    {
        $this->broadcastName = $this->resolveBroadcastName();

        return $this;
    }

    /**
     * Resolve and store the payload property map and the imports its inferred types need.
     */
    protected function transformProperties(): self
    {
        $analysis = $this->runAnalysis();
        $keys = array_column($analysis->properties, 'name');

        $targets = resolve(TsCastsReader::class)->castTargets(
            $this->tsCastsAttributes($this->reflection),
            $keys,
            array_keys($this->tsTypeOverrides),
        );

        $this->tsTypeOverrides = JsEmitter::retargetCasts($this->tsTypeOverrides, $targets);
        $this->tsCastsImportPaths = JsEmitter::retargetCasts($this->tsCastsImportPaths, $targets);
        $this->optionalOverrides = JsEmitter::retargetCasts($this->optionalOverrides, $targets);

        // resolveProperties() lays each cast over its key, and an extends clause adds keys no analysis sees.
        resolve(IndexSignatureReconciler::class)->reconcile(
            $analysis,
            array_intersect_key($this->tsTypeOverrides, array_flip($keys)),
            $this->tsExtends !== [],
        );

        resolve(CastChannels::class)->fit($analysis, $this->castsInForce($analysis));

        $this->properties = $this->resolveProperties($analysis);
        $this->analysisCustomImports = $analysis->customImports;
        $this->analysisImports = new AnalysisImports()->build($analysis, $this->namespacePath)['typeImports'];
        $this->registerCarried($analysis);

        return $this;
    }

    /**
     * Parse #[TsExtends] attribute extends clauses and their import entries from the event class.
     */
    protected function parseTsExtends(): self
    {
        $result = $this->parseTsExtendsFromReflection($this->reflection, 'broadcast_events');

        $this->tsExtends = $result['extends'];
        $this->tsExtendsImports = $result['imports'];

        return $this;
    }

    /**
     * Parse #[TsCasts] attribute overrides from the event class.
     */
    protected function parseTsCasts(): self
    {
        $result = $this->parseTsCastsFromReflection($this->reflection);

        $this->tsTypeOverrides = $result['overrides'];
        $this->tsCastsImportPaths = $result['importPaths'];
        $this->optionalOverrides = $result['optionalOverrides'];

        return $this;
    }

    #[Override]
    public function filename(): string
    {
        return $this->eventName;
    }

    #[Override]
    public function data(): TsBroadcastEventDto
    {
        return new TsBroadcastEventDto(
            eventName: $this->eventName,
            broadcastName: $this->broadcastName,
            fqcn: $this->fqcn(),
            description: '@see '.$this->fqcn(),
            filename: $this->filename(),
            namespacePath: $this->namespacePath,
            properties: $this->properties,
            typeImports: $this->typeImports,
            tsExtends: $this->tsExtends,
        );
    }

    /**
     * Resolve the Echo broadcast event string.
     *
     * Anything but a whole string literal declines to the class-name convention: a computed name
     * folded to its literal prefix would register the event under a key Echo never receives.
     */
    protected function resolveBroadcastName(): string
    {
        $literal = resolve(ReturnLiteralReader::class)->stringLiteral($this->findable, 'broadcastAs');

        return $literal === null || $literal === ''
            ? '.'.str_replace('\\', '.', $this->findable)
            : $literal;
    }

    /**
     * Convert an analysis into the event's payload property map.
     *
     * @return PropertiesList
     */
    protected function resolveProperties(MethodAnalysis $analysis): array
    {
        $this->registerAnalysisFqcns($analysis);

        /** @var PropertiesList $result */
        $result = [];

        foreach ($analysis->properties as $property) {
            $name = $property['name'];

            if (isset($this->tsTypeOverrides[$name])) {
                $result[$name] = JsEmitter::signatureSafeMember(
                    (string) $name,
                    $this->tsTypeOverrides[$name],
                    $this->optionalOverrides[$name] ?? $property['optional'],
                );
                $this->propertyFqcns[$name] = $this->collectPropertyFqcns($name, $analysis);

                continue;
            }

            $result[$name] = [
                'type' => $this->convertClassType($name, $property['type'], $analysis),
                'optional' => $property['optional'],
            ];

            $this->propertyFqcns[$name] = $this->collectPropertyFqcns($name, $analysis);
        }

        return $result;
    }

    /**
     * Track every enum and model FQCN the analysis reached, so imports and aliases can be resolved.
     */
    protected function registerAnalysisFqcns(MethodAnalysis $analysis): void
    {
        foreach ($this->enumFqcns($analysis) as $fqcn) {
            $this->enumFqcnMap[$fqcn] = LaravelTsPublish::toTsType($fqcn)['enumTypes'][0]
                ?? class_basename($fqcn).'Type';
        }

        foreach ($analysis->modelFqcns as $fqcn) {
            $this->modelFqcnMap[$fqcn] = class_basename($fqcn);
        }
    }

    /**
     * Present a property's analysed type the way broadcast events do: a model as Partial<Name>.
     *
     * Enums already render as the #[TsEnum]-aware {Name}Type, so only models need rewriting.
     */
    protected function convertClassType(string $name, string $type, MethodAnalysis $analysis): string
    {
        $modelFqcn = $analysis->modelFqcns[$name] ?? null;

        if ($modelFqcn === null) {
            return $type;
        }

        $typeName = class_basename($modelFqcn);
        $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($typeName, '/').'(?![A-Za-z0-9_$])/';

        return preg_replace($pattern, 'Partial<'.$typeName.'>', $type, 1) ?? $type;
    }

    /**
     * The enum and model FQCNs one property's type references, in the order the type string names them.
     *
     * @return list<class-string>
     */
    protected function collectPropertyFqcns(string $name, MethodAnalysis $analysis): array
    {
        $fqcns = [];

        foreach ([$analysis->directEnumFqcns, $analysis->enumResources, $analysis->modelFqcns] as $map) {
            if (isset($map[$name])) {
                $fqcns[] = $map[$name];
            }
        }

        return [
            ...$fqcns,
            ...($analysis->multiEnumResourceFqcns[$name] ?? []),
            ...($analysis->inlineEnumFqcns[$name] ?? []),
            ...($analysis->inlineModelFqcns[$name] ?? []),
        ];
    }

    /**
     * The cast in force for every key a cast retypes: the event's own, else the last `broadcastWith()` cast.
     *
     * @return CastMap
     */
    protected function castsInForce(MethodAnalysis $analysis): array
    {
        $casts = $analysis->casts;
        $keys = array_flip(array_column($analysis->properties, 'name'));

        // A cast whose key names no payload property publishes nothing, so its import binds no name.
        foreach (array_intersect_key($this->tsTypeOverrides, $keys) as $name => $type) {
            $casts[$name] = ['type' => $type, 'import' => $this->tsCastsImportPaths[$name] ?? null];
        }

        return $casts;
    }

    /**
     * Register each class a cast carried that a payload type or extends clause spells with no class of its name behind
     * it: an enum or a model for aliasing, a resource as an import.
     */
    protected function registerCarried(MethodAnalysis $analysis): void
    {
        $types = array_map(static fn (array $property): string => $property['type'], $this->properties);
        $enumNames = $this->ownNames($this->enumFqcnMap);
        $modelNames = $this->ownNames($this->modelFqcnMap);

        foreach ($analysis->carried['enums'] ?? [] as $fqcn) {
            $typeName = LaravelTsPublish::toTsType($fqcn)['enumTypes'][0] ?? class_basename($fqcn).'Type';

            if (CastChannels::spelledUnclaimed($typeName, $types, $enumNames, $this->tsExtends)) {
                $this->enumFqcnMap[$fqcn] ??= $typeName;
            }
        }

        foreach ($analysis->carried['models'] ?? [] as $fqcn) {
            if (CastChannels::spelledUnclaimed(class_basename($fqcn), $types, $modelNames, $this->tsExtends)) {
                $this->modelFqcnMap[$fqcn] ??= class_basename($fqcn);
            }
        }

        $resourceNames = [];

        foreach (array_keys($this->properties) as $name) {
            $resourceNames[$name] = array_map(TsNaming::resourceTypeName(...), [
                ...(isset($analysis->nestedResources[$name]) ? [$analysis->nestedResources[$name]] : []),
                ...($analysis->inlineResourceFqcns[$name] ?? []),
            ]);
        }

        foreach ($analysis->carried['resources'] ?? [] as $fqcn) {
            $typeName = TsNaming::resourceTypeName($fqcn);

            if (CastChannels::spelledUnclaimed($typeName, $types, $resourceNames, $this->tsExtends)) {
                $importPath = TsNaming::relativeImportPath($this->namespacePath, TsNaming::namespaceToPath($fqcn));
                $this->analysisImports[$importPath] = array_values(array_unique([...($this->analysisImports[$importPath] ?? []), $typeName]));
            }
        }
    }

    /**
     * The names of the registered classes each payload key's import channels hold.
     *
     * @param  array<class-string, string>  $map  FQCN => unaliased name
     * @return array<string, list<string>>
     */
    protected function ownNames(array $map): array
    {
        return array_map(
            static fn (array $fqcns): array => array_values(array_intersect_key($map, array_flip($fqcns))),
            $this->propertyFqcns,
        );
    }

    /**
     * Detect conflicting import names and assign globally-unique aliases via ImportNameRegistry.
     */
    protected function resolveImportConflicts(): self
    {
        $registry = new ImportNameRegistry(['Events', 'Enums', 'Models']);
        $registry->reserve($this->eventName);
        $registry->reserveMany(...$this->customImportNames());

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
        }

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
        }

        $this->applyResolvedImportNames(
            $registry->resolve(),
            $this->enumFqcnMap + $this->modelFqcnMap,
        );

        return $this;
    }

    /**
     * The names this file imports from the app's own modules, which no alias may take: the casts' imports, the extends
     * clauses' and the analysis's.
     *
     * @return list<string>
     */
    protected function customImportNames(): array
    {
        $names = [];

        foreach (array_keys($this->tsCastsImportPaths) as $property) {
            array_push($names, ...TsTypeString::extractImportableTypes($this->tsTypeOverrides[$property] ?? ''));
        }

        foreach ([...array_values($this->tsExtendsImports), ...array_values($this->analysisCustomImports)] as $typeNames) {
            array_push($names, ...$typeNames);
        }

        return array_values(array_unique($names));
    }

    /**
     * Rewrite property type references to use aliases.
     */
    protected function rewriteTypeReferences(): void
    {
        $nameMap = $this->enumFqcnMap + $this->modelFqcnMap;

        foreach ($this->properties as $key => $entry) {
            $this->properties[$key]['type'] = TsTypeString::aliasPropertyType(
                $entry['type'],
                $this->propertyFqcns[$key] ?? [],
                $nameMap,
                $this->importAliases,
            );
        }
    }

    /**
     * Build the TypeScript type import map from every tracked import channel.
     */
    protected function buildTypeImports(): self
    {
        /** @var TypesImportMap $imports */
        $imports = [];

        /** @var array<string, array<string, true>> $aliasable */
        $aliasable = [];

        $types = [...array_column($this->properties, 'type'), ...$this->tsExtends];

        foreach ($this->modelFqcnMap + $this->enumFqcnMap as $fqcn => $typeName) {
            $importPath = TsNaming::relativeImportPath(
                $this->namespacePath,
                TsNaming::namespaceToPath($fqcn),
            );
            $aliasable[$importPath][$typeName] = true;

            // Only a name a type still spells is imported, as ResourceTransformer::pruneUnspelledImports() keeps it.
            if (TsTypeString::typeNameOccursIn($this->localImportName($fqcn, $typeName), ...$types)) {
                $imports[$importPath][] = $this->formatImportName($fqcn, $typeName);
            }
        }

        // Skip what the loop above already settled: only it knows the alias a name collision forced,
        // and an unaliased duplicate would re-import the very name the alias exists to avoid.
        foreach ($this->analysisImports as $importPath => $typeNames) {
            foreach ($typeNames as $typeName) {
                if (! isset($aliasable[$importPath][$typeName])
                    && TsTypeString::typeNameOccursIn($typeName, ...$types)) {
                    $imports[$importPath][] = $typeName;
                }
            }
        }

        foreach ($this->tsCastsImportPaths as $property => $importPath) {
            $type = $this->tsTypeOverrides[$property] ?? null;

            // A cast whose key names no payload property adds none, so its import would go unused.
            if ($type !== null && isset($this->properties[$property])) {
                foreach (TsTypeString::extractImportableTypes($type) as $importName) {
                    $imports[$importPath][] = $importName;
                }
            }
        }

        foreach ($this->tsExtendsImports as $importPath => $typeNames) {
            foreach ($typeNames as $typeName) {
                $imports[$importPath][] = $typeName;
            }
        }

        foreach ($imports as $path => $types) {
            $unique = array_values(array_unique($types));
            sort($unique);
            $imports[$path] = $unique;
        }

        $this->typeImports = TsNaming::sortImportPaths($imports);

        return $this;
    }

    /**
     * Build a map of import aliases to their globally-qualified names.
     *
     * @return array<string, string> alias => 'dot.separated.namespace.TypeName'
     */
    public function globalAliasMap(): array
    {
        $map = [];

        foreach ($this->importAliases as $fqcn => $alias) {
            if (isset($this->enumFqcnMap[$fqcn])) {
                $ns = TsNaming::globalNamespace($fqcn);
                $map[$alias] = $ns.'.'.$this->enumFqcnMap[$fqcn];
            } elseif (isset($this->modelFqcnMap[$fqcn])) {
                $ns = TsNaming::globalNamespace($fqcn);
                $map[$alias] = $ns.'.'.$this->modelFqcnMap[$fqcn];
            }
        }

        return $map;
    }

    /**
     * Map every type token appearing in this event's properties to its globally-qualified name.
     *
     * Name-based qualification alone is ambiguous when one short name exists in several namespaces
     * (App\Models\User vs Crm\Models\User), so GlobalsWriter resolves through this map instead.
     *
     * @return array<string, string> typeName|alias => 'dot.separated.namespace.TypeName'
     */
    public function globalTypeReferenceMap(): array
    {
        return $this->qualifiedImportNames($this->enumFqcnMap, $this->modelFqcnMap);
    }

    /**
     * Every enum FQCN the analysis reached, across all three enum channels.
     *
     * @return list<class-string>
     */
    private function enumFqcns(MethodAnalysis $analysis): array
    {
        $fqcns = [...array_values($analysis->directEnumFqcns), ...array_values($analysis->enumResources)];

        foreach ($analysis->multiEnumResourceFqcns as $branchFqcns) {
            $fqcns = [...$fqcns, ...$branchFqcns];
        }

        return array_values(array_unique($fqcns));
    }
}
