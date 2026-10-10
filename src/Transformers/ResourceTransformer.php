<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ModelClassResolver;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ParsesTsExtends;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\TracksEnumImports;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type PropertiesList from TsResourceDto
 * @phpstan-import-type TypesImportMap from TsResourceDto
 * @phpstan-import-type ValuesImportMap from TsResourceDto
 * @phpstan-import-type ImportMapType from MethodAnalysis
 * @phpstan-import-type EnumResourceArmShapeMap from MethodAnalysis
 * @phpstan-import-type CastMap from MethodAnalysis
 *
 * @phpstan-type EnumResourcePropertyInfo = array{
 *     fqcn: class-string, nullable: bool, isCollection: bool, wrapIsCollection: bool, directIsArray: bool
 * }
 *
 * @extends CoreTransformer<JsonResource>
 */
class ResourceTransformer extends CoreTransformer
{
    use BuildsImportMaps;
    use ParsesTsCasts;
    use ParsesTsExtends;
    use ResolvesImportConflicts;
    use SnapshotsTransformerState;
    use TracksEnumImports {
        shouldGenerateHasEnums as traitShouldGenerateHasEnums;
        enumPropertyFqcns as traitEnumPropertyFqcns;
    }

    public protected(set) string $resourceName;

    public protected(set) string $description = '';

    public protected(set) string $filePath;

    /** @var class-string<Model>|null */
    public protected(set) ?string $modelClass = null;

    /** @var ReflectionClass<JsonResource> */
    public protected(set) ReflectionClass $reflectionResource;

    /** @var PropertiesList */
    public protected(set) array $properties = [];

    /** @var TypesImportMap */
    public protected(set) array $typeImports = [];

    /** @var ValuesImportMap */
    public protected(set) array $valueImports = [];

    /** @var array<string, string> property name => custom type override */
    protected array $tsTypeOverrides = [];

    /** @var ImportMapType custom import path => list of type names */
    protected array $customImports = [];

    /** @var ImportMapType custom imports the AST analysis carried, kept apart until overrides have replaced types */
    protected array $analysisCustomImports = [];

    /** @var array<string, bool> property name => optional override */
    protected array $optionalOverrides = [];

    /** @var array<string, string> property name => import path from the resource's #[TsCasts] */
    protected array $tsCastsImportPaths = [];

    /** @var CastMap property name => the cast in force for a key any #[TsCasts] retypes */
    protected array $castsInForce = [];

    /** @var array<string, true> property name => true, for a key one of the resource's own methods casts */
    protected array $methodCastKeys = [];

    /** @var array<class-string, string> FQCN => resource interface name */
    protected array $resourceFqcnMap = [];

    /**
     * Property => enum info for EnumResource::make()/::collection() properties.
     *
     * @var array<string, EnumResourcePropertyInfo>
     */
    protected array $enumResourceProperties = [];

    /** @var array<class-string, string> FQCN => model interface name */
    protected array $modelFqcnMap = [];

    /** @var array<string, class-string> property name => model FQCN (from bare whenLoaded) */
    protected array $propertyModelFqcns = [];

    /** @var array<string, list<class-string>> property name => list of model FQCNs for union accessor types */
    protected array $propertyModelFqcnsList = [];

    /** @var array<string, list<class-string>> property name => list of enum FQCNs for union enum accessor types */
    protected array $propertyEnumFqcnsList = [];

    /** @var array<string, list<class-string>> property name => enum FQCNs embedded in inline object type strings */
    protected array $propertyInlineEnumFqcns = [];

    /** @var array<string, list<class-string>> property name => model FQCNs embedded in inline object type strings */
    protected array $propertyInlineModelFqcns = [];

    /**
     * Property name => the resource FQCN behind each resource token, in type order.
     *
     * @var array<string, list<class-string>>
     */
    protected array $propertyInlineResourceFqcns = [];

    /** @var array<string, list<class-string>> property name => enum FQCNs embedded via EnumResource in inline object type strings (used for value imports when tolki is enabled) */
    protected array $propertyInlineEnumResourceFqcns = [];

    /** @var array<string, class-string> property name => resource FQCN (from nested resources) */
    protected array $propertyResourceFqcns = [];

    /** @var array<string, class-string> property name => enum FQCN (from direct enum access) */
    protected array $propertyEnumFqcns = [];

    /**
     * Enum FQCNs reachable without an EnumResource wrap, in two entry kinds sharing one map.
     *
     * @var array<string, class-string> property name => FQCN for a ternary/union direct-access branch;
     *                                  FQCN => FQCN for an embedded enum from dispatchFqcnResults()
     */
    protected array $directEnumProperties = [];

    /** @var array<string, list<class-string>> property name => ordered list of enum FQCNs for ternary/union where ALL non-null branches are EnumResource calls with different FQCNs */
    protected array $multiEnumResourceProperties = [];

    /** @var array<string, string> property name => TS type override from model's #[TsCasts] */
    protected array $modelTsCastsOverrides = [];

    /** @var array<string, string> property name => import path from model's #[TsCasts] */
    protected array $modelTsCastsImportPaths = [];

    /** @var array<string, bool> property name => optional flag from model's #[TsCasts] */
    protected array $modelTsCastsOptionalOverrides = [];

    /** @var list<string> TypeScript extends clauses */
    public protected(set) array $tsExtends = [];

    /** @var string|null TypeScript type alias (e.g. `export type X = SingularResource[]`) emitted instead of an interface */
    public protected(set) ?string $typeAlias = null;

    #[Override]
    public function transform(): self
    {
        $this->initReflection()
            ->parseTsExtends()
            ->resolveModelClass()
            ->parseModelTsCastsOverrides()
            ->parseResourceTsCastsOverrides()
            ->runAstAnalysis()
            ->applyOverrides()
            ->dropOverriddenEnumResources()
            ->pruneOverriddenEnumImports()
            ->pruneOverriddenAnalysisImports()
            ->resolveMultiClassAccessorFqcns()
            ->resolveMultiEnumAccessorFqcns()
            ->resolveImportConflicts()
            ->keepWrittenInlineWraps()
            ->rewriteEnumResourceTypes()
            ->pruneUnspelledImports()
            ->buildResolvedImports();

        return $this;
    }

    #[Override]
    public function data(): TsResourceDto
    {
        return new TsResourceDto(
            resourceName: $this->resourceName,
            description: $this->description,
            fqcn: $this->fqcn(),
            filePath: $this->filePath,
            filename: $this->filename(),
            properties: $this->properties,
            typeImports: $this->typeImports,
            valueImports: $this->valueImports,
            modelClass: $this->modelClass,
            tsExtends: $this->tsExtends,
            typeAlias: $this->typeAlias,
        );
    }

    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->resourceName);
    }

    protected function initReflection(): self
    {
        $this->reflectionResource = new ReflectionClass($this->findable);
        $this->filePath = $this->resolveRelativePath((string) $this->reflectionResource->getFileName());
        $this->namespacePath = TsNaming::namespaceToPath($this->findable);

        $tsResourceAttrs = $this->reflectionResource->getAttributes(TsResource::class);

        if ($tsResourceAttrs) {
            $tsResourceInstance = $tsResourceAttrs[0]->newInstance();
            $this->resourceName = $tsResourceInstance->name ?? $this->reflectionResource->getShortName();
            $this->description = $tsResourceInstance->description !== ''
                ? $tsResourceInstance->description
                : JsEmitter::parseDocBlockDescription($this->reflectionResource->getDocComment());
        } else {
            $this->resourceName = $this->reflectionResource->getShortName();
            $this->description = JsEmitter::parseDocBlockDescription($this->reflectionResource->getDocComment());
        }

        return $this;
    }

    protected function parseTsExtends(): self
    {
        $result = $this->parseTsExtendsFromReflection($this->reflectionResource, 'resources');

        $this->tsExtends = $result['extends'];

        foreach ($result['imports'] as $importPath => $typeNames) {
            $this->customImports[$importPath] = [...($this->customImports[$importPath] ?? []), ...$typeNames];
        }

        return $this;
    }

    /**
     * Resolve the backing model class.
     *
     * Delegates to ModelClassResolver so the public AstEngine entry and this pipeline agree.
     */
    protected function resolveModelClass(): self
    {
        $this->modelClass = resolve(ModelClassResolver::class)->resolve($this->reflectionResource);

        return $this;
    }

    /**
     * Parse #[TsCasts] attributes from the backing model for type overrides.
     */
    protected function parseModelTsCastsOverrides(): self
    {
        if ($this->modelClass === null || ! class_exists($this->modelClass)) {
            return $this;
        }

        $result = $this->parseTsCastsFromReflection(new ReflectionClass($this->modelClass));

        $this->modelTsCastsOverrides = $result['overrides'];
        $this->modelTsCastsImportPaths = $result['importPaths'];
        $this->modelTsCastsOptionalOverrides = $result['optionalOverrides'];

        return $this;
    }

    /**
     * Parse #[TsCasts] attributes on the resource class for type overrides.
     */
    protected function parseResourceTsCastsOverrides(): self
    {
        $result = $this->parseTsCastsFromReflection($this->reflectionResource);

        foreach ($result['overrides'] as $property => $type) {
            $this->tsTypeOverrides[$property] = $type;
        }

        $this->tsCastsImportPaths = $result['importPaths'];

        foreach ($result['optionalOverrides'] as $property => $optional) {
            $this->optionalOverrides[$property] = $optional;
        }

        return $this;
    }

    /**
     * Run the AST analyzer on the resource's toArray() method.
     */
    protected function runAstAnalysis(): self
    {
        $analyzer = new ResourceAstAnalyzer($this->reflectionResource, $this->modelClass);
        $analysis = $analyzer->analyze();

        $this->castsOverAnalysisKeys(array_column($analysis->properties, 'name'));
        $this->methodCastKeys = array_fill_keys(array_keys($analysis->casts), true);

        $castKeys = $this->castKeys($analysis);
        $this->castsInForce = $this->collectCastsInForce($castKeys, $analysis);

        // applyOverrides() lays the casts over the analysis, and an extends clause adds keys no analysis sees.
        resolve(IndexSignatureReconciler::class)->reconcile($analysis, $castKeys, $this->tsExtends !== []);
        resolve(CastChannels::class)->fit($analysis, $this->castsInForce);

        // ResourceCollection subclasses with $wrap = null emit an alias, not an interface.
        if ($analysis->flatTypeAlias !== null) {
            $this->typeAlias = $analysis->flatTypeAlias;

            if ($analysis->flatTypeAliasFqcn !== null && $analysis->flatTypeAliasFqcn !== $this->findable) {
                $this->resourceFqcnMap[$analysis->flatTypeAliasFqcn] = TsNaming::resourceTypeName($analysis->flatTypeAliasFqcn);
            }

            return $this;
        }

        foreach ($analysis->properties as $prop) {
            $this->properties[$prop['name']] = [
                'type' => $prop['type'],
                'optional' => $prop['optional'],
                'description' => $prop['description'],
            ];
        }

        foreach ($analysis->enumResources as $propName => $fqcn) {
            $tsInfo = LaravelTsPublish::toTsType($fqcn);
            $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
            $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
            $type = $this->properties[$propName]['type'] ?? '';
            $nullable = str_contains($type, 'null');
            // $type itself may already carry '| null' here, so the suffix check must strip it first.
            $isCollection = str_ends_with(rtrim(str_replace('| null', '', $type)), '[]');
            // ValueResult::withEnumArmShapes() records each arm's own shape for a mixed EnumResource/direct-access
            // ternary or match; anything else mixed (e.g. a `??`) falls back to the merged-string guess, which never
            // marks the wrap arm collection — the behaviour this replaces preserved.
            $armShape = $analysis->enumResourceArmShapes[$propName] ?? null;
            $this->enumResourceProperties[$propName] = [
                'fqcn' => $fqcn,
                'nullable' => $nullable,
                'isCollection' => $isCollection,
                'wrapIsCollection' => $armShape['wrapIsCollection'] ?? false,
                'directIsArray' => $armShape['directIsArray'] ?? $isCollection,
            ];
            $this->propertyEnumFqcns[$propName] = $fqcn;
        }

        foreach ($analysis->directEnumFqcns as $propName => $fqcn) {
            if (! isset($this->enumFqcnMap[$fqcn])) {
                $tsInfo = LaravelTsPublish::toTsType($fqcn);
                $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
                $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
            }
            $this->propertyEnumFqcns[$propName] = $fqcn;
            $this->directEnumProperties[$propName] = $fqcn;
        }

        foreach ($analysis->nestedResources as $propName => $fqcn) {
            if ($fqcn !== $this->findable) {
                $this->resourceFqcnMap[$fqcn] = TsNaming::resourceTypeName($fqcn);
                $this->propertyResourceFqcns[$propName] = $fqcn;
            }
        }

        foreach ($analysis->modelFqcns as $propName => $fqcn) {
            $this->modelFqcnMap[$fqcn] = class_basename($fqcn);
            $this->propertyModelFqcns[$propName] = $fqcn;
        }

        foreach ($analysis->inlineEnumFqcns as $propName => $fqcns) {
            $this->propertyInlineEnumFqcns[$propName] = $fqcns;
        }

        foreach ($analysis->inlineModelFqcns as $propName => $fqcns) {
            $this->propertyInlineModelFqcns[$propName] = $fqcns;
        }

        foreach ($analysis->inlineResourceFqcns as $propName => $fqcns) {
            $this->propertyInlineResourceFqcns[$propName] = $fqcns;
        }

        foreach ($analysis->inlineEnumResourceFqcns as $propName => $fqcns) {
            foreach ($fqcns as $fqcn) {
                if (! isset($this->enumConstMap[$fqcn])) {
                    $tsInfo = LaravelTsPublish::toTsType($fqcn);
                    $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
                }
            }
            $this->propertyInlineEnumResourceFqcns[$propName] = $fqcns;
        }

        foreach ($analysis->multiEnumResourceFqcns as $propName => $fqcns) {
            foreach ($fqcns as $fqcn) {
                if (! isset($this->enumFqcnMap[$fqcn])) {
                    $tsInfo = LaravelTsPublish::toTsType($fqcn);
                    $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
                    $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
                }
            }
            $this->multiEnumResourceProperties[$propName] = $fqcns;
        }

        $this->analysisCustomImports = $analysis->customImports;

        return $this;
    }

    /**
     * The keys applyOverrides() will publish over the analysis: every resource #[TsCasts] key, and each model one
     * modelCastsOver() lays over the analysis's keys.
     *
     * @return array<string, string>
     */
    protected function castKeys(MethodAnalysis $analysis): array
    {
        return $this->tsTypeOverrides + $this->modelCastsOver(array_flip(array_column($analysis->properties, 'name')));
    }

    /**
     * The cast in force for every key a cast retypes, with the text it publishes and whether it brings its own import:
     * the resource's class-level cast, else the last method cast the analysis reports, else the model's.
     *
     * @param  array<string, string>  $castKeys  castKeys()'s resource casts, and model casts over keys no method casts
     * @return CastMap
     */
    protected function collectCastsInForce(array $castKeys, MethodAnalysis $analysis): array
    {
        $casts = $analysis->casts;

        foreach ($castKeys as $property => $type) {
            $import = isset($this->tsTypeOverrides[$property])
                ? isset($this->tsCastsImportPaths[$property])
                : isset($this->modelTsCastsImportPaths[$property]);
            $casts[$property] = ['type' => $type, 'import' => $import];
        }

        return $casts;
    }

    /**
     * The names a cast key's own import brings into the file, which no class of the package may supply.
     *
     * @return list<string>
     */
    protected function castBrings(string $property): array
    {
        $cast = $this->castsInForce[$property] ?? null;

        return $cast !== null && $cast['import'] ? TsTypeString::extractImportableTypes($cast['type']) : [];
    }

    /**
     * The class behind each token of a cast key's text among the given ones, leaving out the names its import brings.
     *
     * @param  list<class-string>  $fqcns
     * @param  Closure(class-string): string  $nameOf
     * @return list<class-string>
     */
    protected function castSpells(string $property, array $fqcns, Closure $nameOf): array
    {
        $brought = $this->castBrings($property);
        $queue = array_values(array_filter(
            $fqcns,
            fn (string $fqcn): bool => ! in_array($nameOf($fqcn), $brought, true),
        ));

        return new ClassTokenQueue($queue, $nameOf)->take($this->castsInForce[$property]['type'] ?? '');
    }

    /**
     * Re-key each #[TsCasts] source to the analysis's spelling of the keys it retypes, then import what the resource's
     * own surviving casts name. One decision per source moves its type, optional and import entries together.
     *
     * @param  list<string>  $keys
     */
    protected function castsOverAnalysisKeys(array $keys): void
    {
        $resource = JsEmitter::castTargets(array_keys($this->tsTypeOverrides), $keys);
        $this->tsTypeOverrides = JsEmitter::retargetCasts($this->tsTypeOverrides, $resource);
        $this->tsCastsImportPaths = JsEmitter::retargetCasts($this->tsCastsImportPaths, $resource);
        $this->optionalOverrides = JsEmitter::retargetCasts($this->optionalOverrides, $resource);

        $model = JsEmitter::castTargets(array_keys($this->modelTsCastsOverrides), $keys);
        $this->modelTsCastsOverrides = JsEmitter::retargetCasts($this->modelTsCastsOverrides, $model);
        $this->modelTsCastsImportPaths = JsEmitter::retargetCasts($this->modelTsCastsImportPaths, $model);
        $this->modelTsCastsOptionalOverrides = JsEmitter::retargetCasts($this->modelTsCastsOptionalOverrides, $model);

        foreach ($this->tsCastsImportPaths as $property => $importPath) {
            foreach (TsTypeString::extractImportableTypes($this->tsTypeOverrides[$property] ?? '') as $importName) {
                $this->customImports[$importPath][] = $importName;
            }
        }
    }

    /**
     * The model #[TsCasts] types that apply to the given keys: each one the model casts and the resource does not, on
     * the class or on one of its methods.
     *
     * @param  array<array-key, mixed>  $keys  the analysis's keys, as array keys
     * @return array<string, string>
     */
    protected function modelCastsOver(array $keys): array
    {
        return array_filter(
            $this->modelTsCastsOverrides,
            fn (int|string $property): bool => isset($keys[$property])
                && ! isset($this->tsTypeOverrides[$property])
                && ! isset($this->methodCastKeys[$property]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Apply model #[TsCasts] then resource #[TsCasts] overrides on top of AST-inferred properties.
     */
    protected function applyOverrides(): self
    {
        foreach ($this->modelCastsOver($this->properties) as $property => $type) {
            $this->properties[$property] = [...$this->properties[$property], 'type' => $type];

            if (isset($this->modelTsCastsImportPaths[$property])) {
                foreach (TsTypeString::extractImportableTypes($type) as $importName) {
                    $this->customImports[$this->modelTsCastsImportPaths[$property]][] = $importName;
                }
            }

            if (isset($this->modelTsCastsOptionalOverrides[$property])) {
                $this->properties[$property]['optional'] = $this->modelTsCastsOptionalOverrides[$property];
            }
        }

        foreach ($this->tsTypeOverrides as $property => $type) {
            if (isset($this->properties[$property])) {
                $this->properties[$property]['type'] = $type;
            } else {
                $this->properties[$property] = [
                    'type' => $type,
                    'optional' => false,
                    'description' => '',
                ];
            }
        }

        foreach ($this->optionalOverrides as $property => $optional) {
            if (isset($this->properties[$property])) {
                $this->properties[$property]['optional'] = $optional;
            }
        }

        return $this;
    }

    /**
     * Drops a key's enum-resource records when its type holds none of their enums' type names, and moves each enum to
     * its inline wraps. Left in place, they make rewriteEnumResourceTypes() throw or import each enum for nothing.
     * This reads $enumFqcnMap before pruneOverriddenEnumImports() removes the entries no type holds.
     *
     * @return $this
     */
    protected function dropOverriddenEnumResources(): self
    {
        // CastChannels::fit() already moved a cast key's records, so only a later spread's key leaves one here.
        foreach ($this->properties as $property => ['type' => $type]) {
            $holdsTypeName = fn (string $fqcn): bool => TsTypeString::typeNameOccursIn(
                $this->enumFqcnMap[$fqcn],
                $type,
            );
            $single = $this->enumResourceProperties[$property]['fqcn'] ?? null;
            $several = $this->multiEnumResourceProperties[$property] ?? [];
            $wraps = $this->propertyInlineEnumResourceFqcns[$property] ?? [];

            if ($single !== null && ! $holdsTypeName($single)) {
                // The other two entries would say this key reads the enum bare, and keep the enum's type import.
                unset(
                    $this->enumResourceProperties[$property],
                    $this->directEnumProperties[$property],
                    $this->propertyEnumFqcns[$property],
                );
                $wraps[] = $single;
            }

            if ($several !== [] && ! array_any($several, $holdsTypeName)) {
                unset($this->multiEnumResourceProperties[$property]);
                array_push($wraps, ...$several);
            }

            // Which wraps the type writes depends on the const aliases, so keepWrittenInlineWraps() cuts the list after
            // resolveImportConflicts().
            if ($wraps !== []) {
                $this->propertyInlineEnumResourceFqcns[$property] = $wraps;
            }
        }

        return $this;
    }

    /**
     * Keeps a key's inline wraps whose const its type writes after `typeof`, by the const's own name or by the alias
     * this file gives it. It runs after resolveImportConflicts(), which decides the aliases.
     *
     * @return $this
     */
    protected function keepWrittenInlineWraps(): self
    {
        foreach ($this->propertyInlineEnumResourceFqcns as $property => $fqcns) {
            $type = $this->properties[$property]['type'] ?? '';
            $writesConst = fn (string $fqcn): bool => preg_match(
                TsTypeString::queuedTokenPattern(
                    array_values(array_unique([
                        $this->enumConstMap[$fqcn],
                        $this->constImportAliases[$fqcn] ?? $this->enumConstMap[$fqcn],
                    ])),
                    'typeof\s+\K',
                ),
                $type,
            ) === 1;

            // A wrap the type writes itself needs its imports and no rewrite, which an inline wrap gets. The list is a
            // queue for aliasTypeofConst(), one entry per occurrence, so it is never deduped.
            $wraps = array_values(array_filter($fqcns, $writesConst));

            if ($wraps === []) {
                unset($this->propertyInlineEnumResourceFqcns[$property]);
            } else {
                $this->propertyInlineEnumResourceFqcns[$property] = $wraps;
            }
        }

        return $this;
    }

    /**
     * Drops enum-map entries whose bare type no property or extends clause names any more after #[TsCasts] overrides.
     *
     * @return $this
     */
    protected function pruneOverriddenEnumImports(): self
    {
        $types = [...array_column($this->properties, 'type'), ...$this->tsExtends];

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            if (! TsTypeString::typeNameOccursIn($typeName, ...$types)) {
                unset($this->enumFqcnMap[$fqcn]);
            }
        }

        return $this;
    }

    /**
     * Drops the model and #[TsType] imports the analysis carried for a name that neither a property type nor an extends
     * clause still spells after #[TsCasts] overrides, which would otherwise be emitted as an unused import.
     *
     * @return $this
     */
    protected function pruneOverriddenAnalysisImports(): self
    {
        // An extends clause names a type as surely as a property does, and can rely on the same import.
        $types = [...array_column($this->properties, 'type'), ...$this->tsExtends];

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            if (! TsTypeString::typeNameOccursIn($typeName, ...$types)) {
                unset($this->modelFqcnMap[$fqcn]);
            }
        }

        foreach ($this->analysisCustomImports as $importPath => $typeNames) {
            foreach ($typeNames as $typeName) {
                if (TsTypeString::typeNameOccursIn($typeName, ...$types)) {
                    $this->customImports[$importPath][] = $typeName;
                }
            }
        }

        return $this;
    }

    /**
     * Drops each model, resource, enum type and custom import whose name, as this file spells it after aliasing, no
     * property type, extends clause or type alias still names: the earlier prunes read names before aliasing.
     *
     * @return $this
     */
    protected function pruneUnspelledImports(): self
    {
        $types = [...array_column($this->properties, 'type'), ...$this->tsExtends, ...array_filter([$this->typeAlias])];

        $spelled = fn (string $typeName, string $fqcn): bool => TsTypeString::typeNameOccursIn(
            $this->localImportName($fqcn, $typeName),
            ...$types,
        );
        $this->enumFqcnMap = array_filter($this->enumFqcnMap, $spelled, ARRAY_FILTER_USE_BOTH);
        $this->resourceFqcnMap = array_filter($this->resourceFqcnMap, $spelled, ARRAY_FILTER_USE_BOTH);
        $this->modelFqcnMap = array_filter($this->modelFqcnMap, $spelled, ARRAY_FILTER_USE_BOTH);

        foreach ($this->customImports as $path => $names) {
            $kept = array_values(array_filter(
                $names,
                fn (string $name): bool => TsTypeString::typeNameOccursIn(Str::afterLast($name, ' as '), ...$types),
            ));

            if ($kept === []) {
                unset($this->customImports[$path]);
            } else {
                $this->customImports[$path] = $kept;
            }
        }

        return $this;
    }

    /**
     * Build the type and value import maps from accumulated FQCNs and custom imports.
     */
    protected function buildResolvedImports(): self
    {
        $typeImports = [];
        $valueImports = [];
        $hasEnums = $this->shouldGenerateHasEnums();

        $typeImports = [
            ...$this->collectModularTypeImports($this->enumFqcnMap),
            ...$this->collectModularTypeImports($this->resourceFqcnMap),
            ...$this->collectModularTypeImports($this->modelFqcnMap),
        ];

        if ($hasEnums) {
            $valueImports = $this->collectModularValueImports($this->enumPropertyFqcns());
        }

        $typeImports = $this->mergeCustomImports($typeImports, $this->customImports);

        $this->typeImports = $this->deduplicateAndSortImports($typeImports);
        $this->valueImports = $this->deduplicateAndSortImports($valueImports);

        return $this;
    }

    /**
     * Rewrite EnumResource::make() property types to AsEnum<typeof Const> when the tolki package is enabled.
     */
    protected function rewriteEnumResourceTypes(): self
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return $this;
        }

        // Snapshotted before the loop: the GC below unsets $this->enumFqcnMap entries as it goes, but
        // a later property sharing the same FQCN as an earlier, GC'd one still needs its bare name to
        // build the mixed union or the substitution search token.
        $originalEnumFqcnMap = $this->enumFqcnMap;

        foreach ($this->enumResourceProperties as $propName => $info) {
            if (! isset($this->properties[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $constName = $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']];
            $isMixed = isset($this->directEnumProperties[$propName]);
            $enumTypeName = $originalEnumFqcnMap[$info['fqcn']];

            if ($isMixed) {
                // Mixed ternary: one branch wraps the enum, the other reads it directly. The
                // analyzer collapses both to a single deduped bare type name, so substitution can't
                // tell the arms apart here — synthesize the union from each arm's own recorded shape.
                $wrappedTypeName = 'AsEnum<typeof '.$constName.'>'.($info['wrapIsCollection'] ? '[]' : '');
                $directTypeName = $enumTypeName.($info['directIsArray'] ? '[]' : '');

                $type = $wrappedTypeName.' | '.$directTypeName;

                if ($info['nullable']) {
                    $type .= ' | null';
                }
            } else {
                // rewriteTypeReferences() already aliased the bare token in $type if this FQCN
                // collided, so the search token must match that alias, not enumFqcnMap's original.
                $searchTypeName = $this->importAliases[$info['fqcn']] ?? $enumTypeName;

                // Substitute the bare enum type-name token inside the analyzer's own type string,
                // so any richer shape (an extra default arm, a keyed Record arm) round-trips
                // untouched — only the wrapped enum's own token changes.
                $type = TsTypeString::substituteEnumType(
                    $this->properties[$propName]['type'],
                    $searchTypeName,
                    'AsEnum<typeof '.$constName.'>',
                );
            }

            $this->properties[$propName] = [
                ...$this->properties[$propName],
                'type' => $type,
            ];

            // The type import survives only if some key still reads this enum bare.
            if (! $isMixed && ! $this->readsEnumDirectly($info['fqcn'])) {
                unset($this->enumFqcnMap[$info['fqcn']]);
            }
        }

        // Ternaries whose non-null branches each wrap a different enum: replace tokens positionally.
        foreach ($this->multiEnumResourceProperties as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $tokens = TsTypeString::splitTopLevelUnion($this->properties[$propName]['type']);
            $fqcnIndex = 0;
            $rewritten = [];

            foreach ($tokens as $token) {
                if ($token === 'null') {
                    $rewritten[] = 'null';
                } elseif (isset($fqcns[$fqcnIndex])) {
                    $fqcn = $fqcns[$fqcnIndex++];
                    $constName = $this->constImportAliases[$fqcn] ?? $this->enumConstMap[$fqcn];
                    $rewritten[] = 'AsEnum<typeof '.$constName.'>';

                    // A mixed ternary elsewhere may still emit XType, which needs the type import.
                    if (! $this->readsEnumDirectly($fqcn)) {
                        unset($this->enumFqcnMap[$fqcn]);
                    }
                } else {
                    $rewritten[] = $token; // @codeCoverageIgnore
                }
            }

            $this->properties[$propName] = [
                ...$this->properties[$propName],
                'type' => implode(' | ', $rewritten),
            ];
        }

        // analyzeInlineArray() already substituted each inline wrap's bare const name into 'AsEnum<typeof {bare}>';
        // rewriteTypeReferences() can't alias it, as $nameMap excludes enumConstMap. Only the name after `typeof` is a
        // const; bare, it is another enum's type. Two inline members can share one bare name, so alias by FQCN order.
        foreach ($this->propertyInlineEnumResourceFqcns as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $this->properties[$propName]['type'] = TsTypeString::aliasTypeofConst(
                $this->properties[$propName]['type'],
                $fqcns,
                $this->enumConstMap,
                $this->constImportAliases,
            );
        }

        return $this;
    }

    /**
     * Whether some key reads the enum bare, at the top level or inside an inline array.
     */
    protected function readsEnumDirectly(string $fqcn): bool
    {
        if (in_array($fqcn, $this->directEnumProperties, true)) {
            return true;
        }

        foreach ($this->propertyEnumFqcns as $property => $propertyFqcn) {
            if ($propertyFqcn === $fqcn && ! isset($this->enumResourceProperties[$property])) {
                return true;
            }
        }

        // An inline array's bare enum is also a FQCN-keyed directEnumProperties entry, but this is the explicit signal.
        return array_any($this->propertyInlineEnumFqcns, static fn (array $fqcns): bool => in_array($fqcn, $fqcns, true));
    }

    /**
     * Register both enum FQCNs of accessors typed Attribute<EnumA|EnumB, never> so they can be aliased.
     */
    protected function resolveMultiEnumAccessorFqcns(): self
    {
        if ($this->modelClass === null) {
            return $this;
        }

        $resolver = resolve(ModelAttributeResolver::class);

        foreach (array_keys($this->properties) as $propName) {
            if (isset($this->propertyEnumFqcnsList[$propName]) || isset($this->propertyInlineEnumFqcns[$propName])) {
                continue;
            }

            // PHP stores a numeric-string key such as '6' as an int.
            $tsInfo = $resolver->resolveAttribute($this->modelClass, (string) $propName);

            if (count($tsInfo['enumFqcns']) < 2) {
                continue;
            }

            /** @var array<class-string, string> $typeNames */
            $typeNames = [];
            $constNames = [];

            foreach ($tsInfo['enumFqcns'] as $i => $fqcn) {
                $typeNames[$fqcn] = $tsInfo['enumTypes'][$i] ?? class_basename($fqcn).'Type';
                $constNames[$fqcn] = $tsInfo['enums'][$i] ?? class_basename($fqcn);
            }

            // A cast key keeps only the enums its own text spells, as CastChannels::fit() leaves the analysis's.
            $fqcns = isset($this->castsInForce[$propName])
                ? $this->castSpells(
                    (string) $propName,
                    array_keys($typeNames),
                    fn (string $fqcn): string => $typeNames[$fqcn],
                )
                : array_keys($typeNames);

            if ($fqcns === []) {
                continue;
            }

            $this->propertyEnumFqcnsList[$propName] = $fqcns;

            foreach ($fqcns as $fqcn) {
                if (! isset($this->enumFqcnMap[$fqcn])) {
                    $this->enumFqcnMap[$fqcn] = $typeNames[$fqcn];
                    $this->enumConstMap[$fqcn] = $constNames[$fqcn];
                }
            }
        }

        return $this;
    }

    /**
     * Register both class FQCNs of accessors typed Attribute<ClassA|ClassB, never> so they can be aliased,
     * and the #[TsType(import:)] paths of the model attribute a property is named after, while its type uses them.
     */
    protected function resolveMultiClassAccessorFqcns(): self
    {
        if ($this->modelClass === null) {
            return $this;
        }

        $resolver = resolve(ModelAttributeResolver::class);
        $modelClass = $this->modelClass;

        foreach (array_keys($this->properties) as $propName) {
            // PHP stores a numeric-string key such as '6' as an int.
            $tsInfo = $resolver->resolveAttribute($modelClass, (string) $propName);

            // An only()/except() filter keeps a key's single model FQCN but drops its #[TsType] imports, so they are
            // registered before the skip below.
            $this->registerModelAttributeCustomImports((string) $propName, $tsInfo['customImports']);

            // Skip when inline analysis already owns this property's FQCNs — letting both maps populate here
            // would double the merged queue and break its prefix alignment with real occurrences.
            if (isset($this->propertyModelFqcns[$propName]) || isset($this->propertyInlineModelFqcns[$propName])) {
                continue;
            }

            if (isset($this->castsInForce[$propName])) {
                $classNames = array_combine($tsInfo['classFqcns'], $tsInfo['classes']);
                $kept = $this->castSpells(
                    (string) $propName,
                    ClassTokenQueue::fqcnsOf($tsInfo),
                    fn (string $fqcn): string => $classNames[$fqcn] ?? class_basename($fqcn),
                );

                if ($kept === []) {
                    continue;
                }

                $this->propertyModelFqcnsList[$propName] = $kept;
                $named = array_intersect($tsInfo['classFqcns'], $kept);
            } else {
                // The key may hold something else under the accessor's name, and an unused import is a tsc error.
                $type = $this->properties[$propName]['type'];
                $named = array_filter(
                    $tsInfo['classFqcns'],
                    fn (int $i): bool => TsTypeString::typeNameOccursIn($tsInfo['classes'][$i], $type),
                    ARRAY_FILTER_USE_KEY,
                );

                if ($named === []) {
                    continue;
                }

                // A class the key's type does not spell is never read: aliasing walks each name's own queue.
                $this->propertyModelFqcnsList[$propName] = ClassTokenQueue::fqcnsOf($tsInfo);
            }

            foreach ($named as $i => $fqcn) {
                /** @var class-string $fqcn */
                if (! isset($this->modelFqcnMap[$fqcn])) {
                    $this->modelFqcnMap[$fqcn] = $tsInfo['classes'][$i]; // @codeCoverageIgnore
                }
            }
        }

        return $this;
    }

    /**
     * Import a model attribute's #[TsType(import:)] names, but only those the emitted property or an extends clause
     * still uses.
     *
     * A resource may override the model's type, and an unused import is a tsc error under noUnusedLocals.
     *
     * @param  array<string, list<string>>  $imports
     */
    protected function registerModelAttributeCustomImports(string $propName, array $imports): void
    {
        foreach ($imports as $path => $names) {
            foreach ($names as $name) {
                $type = $this->properties[$propName]['type'] ?? '';

                if (TsTypeString::typeNameOccursIn($name, $type, ...$this->tsExtends)) {
                    $this->customImports[$path][] = $name;
                }
            }
        }
    }

    /**
     * Detect import name collisions across all FQCN maps and assign aliases.
     */
    protected function resolveImportConflicts(): self
    {
        $skip = ['Models', 'Enums', 'Http', 'Resources', 'App'];

        $registry = new ImportNameRegistry($skip);
        $registry->reserve($this->resourceName);

        // A sibling registry resolves const names rather than string-slicing the type alias, which breaks on a
        // numeric tiebreak suffix.
        $constRegistry = new ImportNameRegistry($skip);

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);

            if (isset($this->enumConstMap[$fqcn])) {
                $constRegistry->register($fqcn, $this->enumConstMap[$fqcn]);
            }
        }

        foreach ($this->resourceFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
        }

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);
        }

        // An inline-only EnumResource FQCN never enters enumFqcnMap, so the loop above never
        // registers its const — register the leftovers here so two inline-only consts (or one
        // inline-only and one top-level) with the same bare name still resolve to distinct names.
        foreach ($this->enumConstMap as $fqcn => $constName) {
            if (! isset($this->enumFqcnMap[$fqcn])) {
                $constRegistry->register($fqcn, $constName);
            }
        }

        $this->applyImportNameRegistries(
            $registry,
            $constRegistry,
            $this->enumFqcnMap + $this->resourceFqcnMap + $this->modelFqcnMap,
            $this->customImports,
        );

        return $this;
    }

    /**
     * Rewrite property type references to use aliased names.
     */
    protected function rewriteTypeReferences(): void
    {
        $nameMap = $this->enumFqcnMap + $this->resourceFqcnMap + $this->modelFqcnMap;

        foreach ($this->mergePropertyFqcnMaps() as $propName => $propFqcns) {
            if (! isset($this->properties[$propName])) {
                continue;
            }

            $this->properties[$propName]['type'] = TsTypeString::aliasPropertyType(
                $this->properties[$propName]['type'],
                $propFqcns,
                $nameMap,
                $this->importAliases,
            );
        }
    }

    /**
     * Merge every per-property FQCN map — singular and list — into a superset-in-order, prefix-aligned
     * FQCN queue: a property in more than one map gets each map's entries concatenated, so its length can
     * exceed real occurrences; aliasPropertyType() consumes only the matching prefix — never dedupe it.
     *
     * @return array<string, list<class-string>>
     */
    protected function mergePropertyFqcnMaps(): array
    {
        /** @var array<string, list<class-string>> $merged */
        $merged = [];

        foreach ([$this->propertyEnumFqcns, $this->propertyResourceFqcns, $this->propertyModelFqcns] as $map) {
            foreach ($map as $propName => $propFqcn) {
                $merged[$propName][] = $propFqcn;
            }
        }

        // The inline lists go models first, then resources: InlineArrayHandler::sharedNames() relies on that order.
        foreach ([
            $this->propertyModelFqcnsList,
            $this->propertyEnumFqcnsList,
            $this->propertyInlineEnumFqcns,
            $this->propertyInlineModelFqcns,
            $this->propertyInlineResourceFqcns,
        ] as $map) {
            foreach ($map as $propName => $propFqcns) {
                $merged[$propName] = [...($merged[$propName] ?? []), ...$propFqcns];
            }
        }

        return $merged;
    }

    /**
     * Build a map of per-file enum const aliases to namespace-qualified type names, walking
     * enumPropertyFqcns() so an inline-only EnumResource FQCN qualifies too, not just top-level
     * and multi ones — otherwise its bare AsEnum<typeof X> leaks into declare global {}.
     *
     * @return array<string, string> constAlias => 'namespace.TypeName'
     */
    public function globalEnumConstMap(): array
    {
        $map = [];

        foreach ($this->enumPropertyFqcns() as $fqcn) {
            $constAlias = $this->constImportAliases[$fqcn] ?? $this->enumConstMap[$fqcn] ?? null;

            // rewriteEnumResourceTypes() may have cleared enumFqcnMap; enumConstMap is never cleared.
            $originalConstName = $this->enumConstMap[$fqcn] ?? null;

            if ($constAlias === null || $originalConstName === null) {
                continue; // @codeCoverageIgnore
            }

            $typeName = $originalConstName.'Type';
            $ns = TsNaming::globalNamespace($fqcn);

            $map[$constAlias] = $ns.'.'.$typeName;
        }

        return $map;
    }

    /**
     * Build a map of per-file import aliases → namespace-qualified global names.
     *
     * @return array<string, string> alias => 'namespace.OriginalName'
     */
    public function globalAliasMap(): array
    {
        $map = [];

        foreach ($this->importAliases as $fqcn => $alias) {
            if (isset($this->enumFqcnMap[$fqcn])) {
                $ns = TsNaming::globalNamespace($fqcn);
                $map[$alias] = $ns.'.'.$this->enumFqcnMap[$fqcn];
            } elseif (isset($this->resourceFqcnMap[$fqcn])) {
                $ns = TsNaming::globalNamespace($fqcn);
                $map[$alias] = $ns.'.'.$this->resourceFqcnMap[$fqcn];
            } elseif (isset($this->modelFqcnMap[$fqcn])) {
                $ns = TsNaming::globalNamespace($fqcn);
                $map[$alias] = $ns.'.'.$this->modelFqcnMap[$fqcn];
            }
        }

        return $map;
    }

    /**
     * Map every type name this resource's file imports, as the file spells it, to its globally-qualified name.
     *
     * @return array<string, string> typeName|alias => 'dot.separated.namespace.TypeName'
     */
    public function globalTypeReferenceMap(): array
    {
        return $this->qualifiedImportNames($this->enumFqcnMap, $this->resourceFqcnMap, $this->modelFqcnMap);
    }

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return ['reflectionResource'];
    }

    #[Override]
    protected function enumProperties(): array
    {
        return $this->enumResourceProperties;
    }

    /**
     * Whether to generate HasEnums value imports, also counting enums wrapped inside unions and inline object types.
     */
    protected function shouldGenerateHasEnums(): bool
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return false;
        }

        return $this->enumProperties() !== []
            || $this->multiEnumResourceProperties !== []
            || $this->propertyInlineEnumResourceFqcns !== [];
    }

    /**
     * Return unique enum FQCNs for value-import generation, including multi-enum and inline ones.
     *
     * @return list<string>
     */
    protected function enumPropertyFqcns(): array
    {
        $base = $this->traitEnumPropertyFqcns();

        $multi = [];

        foreach ($this->multiEnumResourceProperties as $fqcns) {
            foreach ($fqcns as $fqcn) {
                $multi[] = $fqcn;
            }
        }

        $inlineResources = [];

        foreach ($this->propertyInlineEnumResourceFqcns as $fqcns) {
            foreach ($fqcns as $fqcn) {
                $inlineResources[] = $fqcn;
            }
        }

        return array_values(array_unique([...$base, ...$multi, ...$inlineResources]));
    }
}
