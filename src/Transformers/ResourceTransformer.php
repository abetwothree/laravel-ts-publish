<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumNames;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumWrapImports;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ModelClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\VariantUnions;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Support\VariantRows;
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
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type CarriedMap from MethodAnalysis
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type ClauseEntry from ParsesTsExtends
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
    use TracksEnumImports;

    public protected(set) string $resourceName;

    public protected(set) string $description = '';

    public protected(set) string $filePath;

    /** @var class-string<Model>|null */
    public protected(set) ?string $modelClass = null;

    /** @var ReflectionClass<JsonResource> */
    public protected(set) ReflectionClass $reflectionResource;

    /** @var PropertiesList the rows every arm shares, once transform() has moved the variant rows out */
    public protected(set) array $properties = [];

    /** @var list<string> each group's union of one object per value of the key the group follows */
    public protected(set) array $variants = [];

    /** @var TypesImportMap */
    public protected(set) array $typeImports = [];

    /** @var ValuesImportMap */
    public protected(set) array $valueImports = [];

    /** @var list<string> TypeScript extends clauses */
    public protected(set) array $tsExtends = [];

    /** @var list<string> the TypeScript types the resource's type is intersected with */
    public protected(set) array $intersections = [];

    /** @var string|null TypeScript type alias (e.g. `export type X = SingularResource[]`) emitted instead of an interface */
    public protected(set) ?string $typeAlias = null;

    /** Whether the resource's own #[TsResource] turns its variants on or off; null leaves it to the config. */
    protected ?bool $discriminated = null;

    /** @var array<array-key, string> property name => custom type override */
    protected array $tsTypeOverrides = [];

    /** @var ImportMapType custom import path => list of type names */
    protected array $customImports = [];

    /** @var ImportMapType custom imports the AST analysis carried, kept apart until overrides have replaced types */
    protected array $analysisCustomImports = [];

    /** @var list<ClauseEntry> the clauses as read, whose imports wait for the names the file binds by other means */
    protected array $clauseEntries = [];

    /** @var array<array-key, bool> property name => optional override */
    protected array $optionalOverrides = [];

    /** @var array<array-key, string> property name => import path from the resource's #[TsCasts] */
    protected array $tsCastsImportPaths = [];

    /** @var CastMap property name => the cast in force for a key any #[TsCasts] retypes */
    protected array $castsInForce = [];

    /** @var CastMap property name => the cast one of the resource's own methods declares for the key */
    protected array $methodCasts = [];

    /** @var CarriedMap the classes the casts displaced and no queue holds, registered only while spelled */
    protected array $carried = [];

    /** @var array<array-key, Slots> property name => the class behind each class token of a declaration's text */
    protected array $declaredTokenFqcns = [];

    /** @var array<class-string, string> FQCN => resource interface name */
    protected array $resourceFqcnMap = [];

    /** @var array<class-string, string> FQCN => model interface name */
    protected array $modelFqcnMap = [];

    /** @var array<array-key, class-string> property name => model FQCN (from bare whenLoaded) */
    protected array $propertyModelFqcns = [];

    /** @var array<array-key, list<class-string>> property name => list of model FQCNs for union accessor types */
    protected array $propertyModelFqcnsList = [];

    /** @var array<array-key, list<class-string>> property name => list of enum FQCNs for union enum accessor types */
    protected array $propertyEnumFqcnsList = [];

    /** @var array<array-key, list<class-string>> property name => enum FQCNs embedded in inline object type strings */
    protected array $propertyInlineEnumFqcns = [];

    /** @var array<array-key, list<class-string>> property name => model FQCNs embedded in inline object type strings */
    protected array $propertyInlineModelFqcns = [];

    /**
     * Property name => the resource FQCN behind each resource token, in type order.
     *
     * @var array<array-key, list<class-string>>
     */
    protected array $propertyInlineResourceFqcns = [];

    /** @var array<array-key, list<class-string>> property name => enum FQCNs embedded via EnumResource in inline object type strings (used for value imports when tolki is enabled) */
    protected array $propertyInlineEnumResourceFqcns = [];

    /** @var array<array-key, class-string> property name => resource FQCN (from nested resources) */
    protected array $propertyResourceFqcns = [];

    /** @var array<array-key, class-string> property name => enum FQCN (from direct enum access) */
    protected array $propertyEnumFqcns = [];

    /** @var array<array-key, string> property name => TS type override from model's #[TsCasts] */
    protected array $modelTsCastsOverrides = [];

    /** @var array<array-key, string> property name => import path from model's #[TsCasts] */
    protected array $modelTsCastsImportPaths = [];

    /** @var array<array-key, bool> property name => optional flag from model's #[TsCasts] */
    protected array $modelTsCastsOptionalOverrides = [];

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
            ->pruneOverriddenEnumImports()
            ->pruneOverriddenAnalysisImports()
            ->resolveMultiClassAccessorFqcns()
            ->resolveMultiEnumAccessorFqcns()
            ->settleClauseImports()
            ->resolveImportConflicts()
            ->keepWrittenInlineWraps()
            ->rewriteEnumResourceTypes()
            ->pruneUnspelledImports()
            ->buildResolvedImports()
            ->splitVariants();

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
            variants: $this->variants,
            intersections: $this->intersections,
        );
    }

    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->resourceName);
    }

    /**
     * The composed classes whose module this resource's file imports, read from its custom imports.
     *
     * @return list<class-string>
     */
    #[Override]
    public function composedClassTypes(): array
    {
        return ComposedClassTypes::classesIn($this->customImports, $this->analysisCustomImports);
    }

    /**
     * The interface or type alias the resource's file declares.
     *
     * @return list<string>
     */
    #[Override]
    public function declaredNames(): array
    {
        return [$this->resourceName];
    }

    /**
     * Build a map of per-file enum const aliases to namespace-qualified type names, walking enumPropertyFqcns() so no
     * wrap's bare AsEnum<typeof X> leaks into declare global {}.
     *
     * @return array<string, string> constAlias => 'namespace.TypeName'
     */
    public function globalEnumConstMap(): array
    {
        return $this->qualifiedConstTypes($this->enumPropertyFqcns());
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

        return $map + $this->siblingGlobalAliases();
    }

    /**
     * Map every type name this resource's file imports, as the file spells it, to its globally-qualified name.
     *
     * @return array<string, string> typeName|alias => 'dot.separated.namespace.TypeName'
     */
    public function globalTypeReferenceMap(): array
    {
        return $this->qualifiedImportNames($this->enumFqcnMap, $this->resourceFqcnMap, $this->modelFqcnMap)
            + ComposedClassTypes::globalReferences($this->composedClassTypes());
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
            $this->discriminated = $tsResourceInstance->discriminated;
        } else {
            $this->resourceName = $this->reflectionResource->getShortName();
            $this->description = JsEmitter::parseDocBlockDescription($this->reflectionResource->getDocComment());
        }

        return $this;
    }

    /**
     * Read the resource's #[TsExtends] clauses and config entries and its #[TsIntersect] types. Until
     * settleClauseImports() runs, each step before it reads every clause as written.
     */
    protected function parseTsExtends(): self
    {
        $this->clauseEntries = $this->readClauses(
            $this->reflectionResource,
            [TsExtends::class => 'resources', TsIntersect::class => null],
        );
        $texts = array_fill_keys([TsExtends::class, TsIntersect::class], []);

        foreach ($this->clauseEntries as $entry) {
            $texts[$entry['attribute']][] = $entry['clause'];
        }

        $this->tsExtends = $texts[TsExtends::class];
        $this->intersections = $texts[TsIntersect::class];

        return $this;
    }

    /**
     * Every name a clause spells, #104's reading, beside each name it lists whole, such as a global's name a clause is:
     * settleClauseImports() leaves to the file each name it binds itself.
     *
     * @return list<string>
     */
    protected function clauseTypeNames(string $clause): array
    {
        $names = [...$this->listedTypeNames($clause), ...TsTypeString::referencedTypeNames($clause)];

        return array_values(array_unique($names));
    }

    /**
     * Settle the clauses once every other import and the package's own are known: a name the file binds by those
     * keeps that binding, and every other name imports from its clause's path, aliased across both attributes and
     * apart from the name the file declares.
     */
    protected function settleClauseImports(): self
    {
        $consts = $this->shouldGenerateHasEnums() ? array_flip($this->enumPropertyFqcns()) : [];
        $names = [
            ...array_values($this->resourceFqcnMap),
            ...array_values($this->modelFqcnMap),
            ...array_merge([], ...array_map(EnumNames::names(...), array_keys($this->enumFqcnMap))),
            ...array_values(array_intersect_key($this->enumConstMap, $consts)),
        ];
        // Two classes of one name are both aliased, so that name binds neither.
        $classes = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count === 1));
        // The template imports `AsEnum` from the package beside an enum's const; a clause spelling it reads that one.
        $packageNames = [...array_map(strval(...), $classes), ...($consts === [] ? [] : ['AsEnum'])];
        $result = $this->settleClauses($this->clauseEntries, $this->customImports, $packageNames, $this->declaredNames());
        $this->tsExtends = $result['clauses'][TsExtends::class];
        $this->intersections = $result['clauses'][TsIntersect::class];

        foreach ($result['imports'] as $importPath => $typeNames) {
            $this->customImports[$importPath] = [...($this->customImports[$importPath] ?? []), ...$typeNames];
        }

        return $this;
    }

    /**
     * The extends clauses and the intersected types, which name a type as surely as a property does.
     *
     * @return list<string>
     */
    protected function clauses(): array
    {
        return [...$this->tsExtends, ...$this->intersections];
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
        if ($this->modelClass === null || ! class_exists($this->modelClass) || $this->castsRetypeAttributes()) {
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
        if ($this->castsRetypeAttributes()) {
            return $this;
        }

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
     * Whether the class and model #[TsCasts] retype a JSON:API resource's `attributes` members, which its analysis lays
     * them over, so none of them retypes a top-level key such as `id` or `type`.
     */
    protected function castsRetypeAttributes(): bool
    {
        return JsonApiResources::is($this->findable);
    }

    /**
     * Run the AST analyzer on the resource's toArray() method.
     */
    protected function runAstAnalysis(): self
    {
        $analyzer = new ResourceAstAnalyzer($this->reflectionResource, $this->modelClass);
        $analysis = $analyzer->analyze();
        ResourceAstAnalyzer::reindexNumericKeyedArrays($analysis, $this->reflectionResource);

        $this->castsOverAnalysisKeys(array_column($analysis->properties, 'name'));
        $this->methodCasts = $analysis->casts;

        $castKeys = $this->castKeys($analysis);
        $this->castsInForce = $this->collectCastsInForce($castKeys, $analysis);

        if ($this->publishesVariants()) {
            VariantUnions::apply($analysis, array_map(strval(...), array_keys($this->castsInForce)));
        }

        // applyOverrides() lays the casts over the analysis, and an extends clause adds keys no analysis sees. A method
        // cast counts too, since CastChannels::fit() settles its key's import channels only after the reconcile.
        resolve(IndexSignatureReconciler::class)->reconcile(
            $analysis,
            array_map(fn (array $cast): string => $cast['type'], $this->castsInForce),
            $this->tsExtends !== [],
            $this->findable,
        );
        $analysis->absorbRedundantArms($this->queuesAccessorClasses(...));
        resolve(CastChannels::class)->fit($analysis, $this->castsInForce);
        $this->carried = $analysis->carried;
        $this->declaredTokenFqcns = $analysis->declaredTokenFqcns;

        // A body-less collection whose live wrap key is null or empty emits an alias, not an interface.
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

        foreach ($analysis->directEnumFqcns as $propName => $fqcn) {
            if (! isset($this->enumFqcnMap[$fqcn])) {
                $this->enumFqcnMap[$fqcn] = EnumNames::typeName($fqcn);
                $this->enumConstMap[$fqcn] = EnumNames::constName($fqcn);
            }
            $this->propertyEnumFqcns[$propName] = $fqcn;
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
                $this->enumConstMap[$fqcn] ??= EnumNames::constName($fqcn);
            }
            $this->propertyInlineEnumResourceFqcns[$propName] = $fqcns;
        }

        $this->analysisCustomImports = $analysis->customImports;

        return $this;
    }

    /**
     * Whether the resource publishes each group of keys that follow a key it sends as one arm per value: its own
     * #[TsResource] says, else the config.
     */
    protected function publishesVariants(): bool
    {
        return $this->discriminated ?? Config::boolean('ts-publish.resources.discriminated_unions', true);
    }

    /**
     * The keys applyOverrides() will publish over the analysis: every resource #[TsCasts] key, and each model one
     * modelCastsOver() lays over the analysis's keys.
     *
     * @return array<array-key, string>
     */
    protected function castKeys(MethodAnalysis $analysis): array
    {
        return $this->tsTypeOverrides + $this->modelCastsOver(array_flip(array_column($analysis->properties, 'name')));
    }

    /**
     * The cast in force for every key a cast retypes, with the text it publishes and the path of its own import:
     * the resource's class-level cast, else the last method cast the analysis reports, else the model's.
     *
     * @param  array<array-key, string>  $castKeys  resource casts, and model casts over keys no method casts
     * @return CastMap
     */
    protected function collectCastsInForce(array $castKeys, MethodAnalysis $analysis): array
    {
        $casts = $analysis->casts;

        foreach ($castKeys as $property => $type) {
            $import = isset($this->tsTypeOverrides[$property])
                ? ($this->tsCastsImportPaths[$property] ?? null)
                : ($this->modelTsCastsImportPaths[$property] ?? null);
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
        return isset($this->castsInForce[$property]) ? CastChannels::brings($this->castsInForce[$property]) : [];
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
        return CastChannels::spells(
            $fqcns,
            $this->castsInForce[$property]['type'] ?? '',
            $this->castBrings($property),
            $nameOf,
        );
    }

    /**
     * Re-key each #[TsCasts] source to the analysis's spelling of the keys it retypes, each location deciding alone
     * so a later one outranks an earlier one, then import what the resource's own surviving casts name. One decision
     * per source moves its type, optional and import entries together; a key no attribute names decides last.
     *
     * @param  list<string>  $keys
     */
    protected function castsOverAnalysisKeys(array $keys): void
    {
        $reader = resolve(TsCastsReader::class);

        $resource = $reader->castTargets(
            $this->tsCastsAttributes($this->reflectionResource),
            $keys,
            array_keys($this->tsTypeOverrides),
        );
        JsEmitter::warnAmbiguousCasts($this->findable, array_keys($this->tsTypeOverrides), $keys);
        $this->tsTypeOverrides = JsEmitter::retargetCasts($this->tsTypeOverrides, $resource);
        $this->tsCastsImportPaths = JsEmitter::retargetCasts($this->tsCastsImportPaths, $resource);
        $this->optionalOverrides = JsEmitter::retargetCasts($this->optionalOverrides, $resource);

        $modelAttributes = $this->modelClass !== null && class_exists($this->modelClass)
            ? $this->tsCastsAttributes(new ReflectionClass($this->modelClass))
            : [];
        $model = $reader->castTargets($modelAttributes, $keys, array_keys($this->modelTsCastsOverrides));
        JsEmitter::warnAmbiguousCasts($this->findable, array_keys($this->modelTsCastsOverrides), $keys);
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
     * @return array<array-key, string>
     */
    protected function modelCastsOver(array $keys): array
    {
        return array_filter(
            $this->modelTsCastsOverrides,
            fn (int|string $property): bool => isset($keys[$property])
                && ! isset($this->tsTypeOverrides[$property])
                && ! isset($this->methodCasts[$property]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Apply model #[TsCasts] then resource #[TsCasts] overrides on top of AST-inferred properties.
     */
    protected function applyOverrides(): self
    {
        // The model cast's flag holds on each key the analysis has, unless one of the resource's own casts sets a flag.
        $modelOptional = array_filter(
            array_intersect_key($this->modelTsCastsOptionalOverrides, $this->properties),
            fn (int|string $property): bool => ! isset($this->methodCasts[$property]['optional']),
            ARRAY_FILTER_USE_KEY,
        );

        foreach ($this->modelCastsOver($this->properties) as $property => $type) {
            $this->properties[$property] = [...$this->properties[$property], 'type' => $type];

            if (isset($this->modelTsCastsImportPaths[$property])) {
                foreach (TsTypeString::extractImportableTypes($type) as $importName) {
                    $this->customImports[$this->modelTsCastsImportPaths[$property]][] = $importName;
                }
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

        foreach (array_replace($modelOptional, $this->optionalOverrides) as $property => $optional) {
            if (isset($this->properties[$property])) {
                $this->properties[$property] = [
                    ...$this->properties[$property],
                    ...JsEmitter::signatureSafeMember((string) $property, $this->properties[$property]['type'], $optional),
                ];
            }
        }

        return $this;
    }

    /**
     * Keeps a key's inline wraps whose const its type writes after `typeof`, by the const's own name or by the alias
     * this file gives it, one per token. It runs after resolveImportConflicts(), which decides the aliases.
     *
     * @return $this
     */
    protected function keepWrittenInlineWraps(): self
    {
        foreach ($this->propertyInlineEnumResourceFqcns as $property => $fqcns) {
            // The list is a queue for aliasTypeofConst(), one entry per occurrence, so it is never deduped.
            $wraps = EnumWrapImports::written($this->properties[$property]['type'] ?? '', $fqcns, $this->constImportAliases);

            if ($wraps === []) {
                unset($this->propertyInlineEnumResourceFqcns[$property]);
            } else {
                $this->propertyInlineEnumResourceFqcns[$property] = $wraps;
            }
        }

        return $this;
    }

    /**
     * Drops the enum-map entries no property or clause still needs after #[TsCasts] overrides.
     *
     * @return $this
     */
    protected function pruneOverriddenEnumImports(): self
    {
        $attributed = [];

        foreach (array_keys($this->properties) as $property) {
            $attributed[$property] = array_values(array_filter([
                $this->propertyEnumFqcns[$property] ?? null,
                ...($this->propertyInlineEnumFqcns[$property] ?? []),
            ]));
        }

        $enumTypeName = EnumNames::typeName(...);

        foreach ($this->carriedInUse('enums', $this->enumFqcnMap, $attributed, $this->clauses(), $enumTypeName) as $fqcn => $typeName) {
            $this->enumFqcnMap[$fqcn] ??= $typeName;
            $this->enumConstMap[$fqcn] ??= EnumNames::constName($fqcn);
        }

        $this->enumFqcnMap = $this->keepSpelled($this->enumFqcnMap, $attributed, $this->clauses(), EnumNames::names(...));

        return $this;
    }

    /**
     * Drops the model and resource imports no property or clause still needs after #[TsCasts] overrides, and
     * keeps each custom import the analysis carried while a type spells its name.
     *
     * @return $this
     */
    protected function pruneOverriddenAnalysisImports(): self
    {
        // A clause names a type as surely as a property does, and can rely on the same import.
        $types = [...array_column($this->properties, 'type'), ...$this->clauses()];

        $models = [];
        $resources = [];

        foreach (array_keys($this->properties) as $property) {
            $models[$property] = array_values(array_filter([
                $this->propertyModelFqcns[$property] ?? null,
                ...($this->propertyInlineModelFqcns[$property] ?? []),
            ]));
            $resources[$property] = array_values(array_filter([
                $this->propertyResourceFqcns[$property] ?? null,
                ...($this->propertyInlineResourceFqcns[$property] ?? []),
            ]));
        }

        // A collection's flat type alias spells the resource it collects, with no property behind it.
        $resourceExtra = [...$this->clauses(), ...array_filter([$this->typeAlias])];
        $resourceName = static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn);

        foreach ($this->carriedInUse('models', $this->modelFqcnMap, $models, $this->clauses(), class_basename(...)) as $fqcn => $name) {
            $this->modelFqcnMap[$fqcn] ??= $name;
        }

        foreach ($this->carriedInUse('resources', $this->resourceFqcnMap, $resources, $resourceExtra, $resourceName) as $fqcn => $name) {
            if ($fqcn !== $this->findable) {
                $this->resourceFqcnMap[$fqcn] ??= $name;
            }
        }

        $this->modelFqcnMap = $this->keepSpelled($this->modelFqcnMap, $models, $this->clauses());
        $this->resourceFqcnMap = $this->keepSpelled($this->resourceFqcnMap, $resources, $resourceExtra);

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
     * Keeps each class a clause or type alias spells, or a property whose type spells it and whose own import
     * channels carry it or no other class of its name: a class no key reads, as a cast displaced it, then never forces
     * an alias on the one a key reads.
     *
     * @template TFqcn of string
     *
     * @param  array<TFqcn, string>  $map  FQCN => unaliased name
     * @param  array<array-key, list<string>>  $attributed  property => this kind's classes its import channels carry
     * @param  list<string>  $extra  the clauses, and for resources the type alias
     * @param  (Closure(TFqcn): list<string>)|null  $namesOf  every name a class's tokens are spelled with; null for
     *                                                        its unaliased name alone
     * @return array<TFqcn, string>
     */
    protected function keepSpelled(array $map, array $attributed, array $extra, ?Closure $namesOf = null): array
    {
        $types = array_map(static fn (array $property): string => $property['type'], $this->properties);
        $ownNames = $this->ownNames($map, $attributed);

        return array_filter($map, function (string $name, string $fqcn) use ($attributed, $types, $ownNames, $extra, $namesOf): bool {
            $names = $namesOf === null ? [$name] : $namesOf($fqcn);

            foreach ($types as $property => $type) {
                if (in_array($fqcn, $attributed[$property] ?? [], true)
                    && array_any($names, static fn (string $spelled): bool => TsTypeString::typeNameOccursIn($spelled, $type))) {
                    return true;
                }
            }

            return CastChannels::spelledUnclaimed($name, $types, $ownNames, $extra);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Each carried class of one kind some published text spells with no class of its name behind it, by that name.
     *
     * @param  'enums'|'models'|'resources'  $kind
     * @param  array<string, string>  $map  FQCN => unaliased name, of this kind's registered classes
     * @param  array<array-key, list<string>>  $attributed  property => this kind's classes its import channels carry
     * @param  list<string>  $extra  the clauses, and for resources the type alias
     * @param  Closure(class-string): string  $nameOf
     * @return array<class-string, string>
     */
    protected function carriedInUse(string $kind, array $map, array $attributed, array $extra, Closure $nameOf): array
    {
        $types = array_map(static fn (array $property): string => $property['type'], $this->properties);
        $ownNames = $this->ownNames($map, $attributed);
        $inUse = [];

        foreach ($this->carried[$kind] ?? [] as $fqcn) {
            if (CastChannels::spelledUnclaimed($nameOf($fqcn), $types, $ownNames, $extra)) {
                $inUse[$fqcn] = $nameOf($fqcn);
            }
        }

        return $inUse;
    }

    /**
     * The names of the registered classes each property's import channels carry.
     *
     * @param  array<string, string>  $map  FQCN => unaliased name
     * @param  array<array-key, list<string>>  $attributed  property => this kind's classes its import channels carry
     * @return array<array-key, list<string>>
     */
    protected function ownNames(array $map, array $attributed): array
    {
        return array_map(static fn (array $own): array => array_values(array_intersect_key($map, array_flip($own))), $attributed);
    }

    /**
     * Drops each model, resource, enum type and custom import whose name, as this file spells it after aliasing, no
     * property type, clause or type alias still names: the earlier prunes read names before aliasing.
     *
     * @return $this
     */
    protected function pruneUnspelledImports(): self
    {
        $types = [...array_column($this->properties, 'type'), ...$this->clauses(), ...array_filter([$this->typeAlias])];

        $spelled = fn (string $typeName, string $fqcn): bool => array_any(
            $this->localImportNames($fqcn, $typeName),
            static fn (string $local): bool => TsTypeString::typeNameOccursIn($local, ...$types),
        );
        $this->enumFqcnMap = array_filter($this->enumFqcnMap, $spelled, ARRAY_FILTER_USE_BOTH);
        $this->resourceFqcnMap = array_filter($this->resourceFqcnMap, $spelled, ARRAY_FILTER_USE_BOTH);
        $this->modelFqcnMap = array_filter($this->modelFqcnMap, $spelled, ARRAY_FILTER_USE_BOTH);

        foreach ($this->customImports as $path => $names) {
            $kept = array_values(array_filter(
                $names,
                fn (string $name): bool => TsTypeString::typeNameOccursIn(ImportMaps::localName($name), ...$types),
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

        $types = [...array_column($this->properties, 'type'), ...$this->clauses(), ...array_filter([$this->typeAlias])];

        $typeImports = [
            ...$this->collectModularTypeImports($this->enumFqcnMap, $types),
            ...$this->collectModularTypeImports($this->resourceFqcnMap),
            ...$this->collectModularTypeImports($this->modelFqcnMap),
        ];

        if ($hasEnums) {
            $valueImports = $this->collectModularValueImports($this->enumPropertyFqcns(), $types);
        }

        $typeImports = ComposedClassTypes::resolveImports($this->customImports, $this->namespacePath, $typeImports);

        $this->typeImports = $this->deduplicateAndSortImports($typeImports);
        $this->valueImports = $this->deduplicateAndSortImports($valueImports);

        return $this;
    }

    /**
     * Move each variant row out of the properties into the variants, so no reader of the published rows, a template
     * published before variants existed among them, meets a reserved row's name.
     */
    protected function splitVariants(): self
    {
        [$this->properties, $this->variants] = VariantRows::split($this->properties);

        return $this;
    }

    /**
     * Respell each const a wrap's `AsEnum<typeof Const>` writes by the alias this file gives it, with the tolki package
     * on: rewriteTypeReferences() cannot, as its name map holds type names, never const ones.
     *
     * Only the name after `typeof` is a const; bare, it is another enum's type. Two wraps can share one bare name, so
     * each token is aliased by its queue's FQCN order.
     */
    protected function rewriteEnumResourceTypes(): self
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return $this;
        }

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
     * Whether resolveMultiClassAccessorFqcns() or resolveMultiEnumAccessorFqcns() may queue classes on a key once its
     * analysis is done: the classes, or two enums, of the model attribute it is named after.
     */
    protected function queuesAccessorClasses(string $key): bool
    {
        if ($this->modelClass === null) {
            return false;
        }

        $info = resolve(ModelAttributeResolver::class)->resolveAttribute($this->modelClass, $key);

        return $info['classFqcns'] !== [] || count($info['enumFqcns']) > 1;
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
                    $this->modelFqcnMap[$fqcn] = $tsInfo['classes'][$i];
                }
            }
        }

        return $this;
    }

    /**
     * Import a model attribute's #[TsType(import:)] names, but only those the emitted property or a clause
     * still uses.
     *
     * A resource may override the model's type, and an unused import is a tsc error under noUnusedLocals.
     *
     * @param  array<string, list<string>>  $imports
     */
    protected function registerModelAttributeCustomImports(string $propName, array $imports): void
    {
        // A name a cast key's own import brings is the app's: the attribute's import of that name would duplicate it.
        $brought = $this->castBrings($propName);

        foreach ($imports as $path => $names) {
            foreach ($names as $name) {
                $type = $this->properties[$propName]['type'] ?? '';

                if (! in_array($name, $brought, true)
                    && TsTypeString::typeNameOccursIn($name, $type, ...$this->clauses())) {
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

        // A sibling registry resolves const names rather than string-slicing the type alias, which breaks on a
        // numeric tiebreak suffix.
        $constRegistry = new ImportNameRegistry($skip);
        $this->reserveDeclaredNames($this->declaredNames(), $registry, $constRegistry);

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

        $this->registerEnumKinds($registry, $this->enumFqcnMap, $this->properties, $this->mergePropertyFqcnMaps());

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

            $type = $this->properties[$propName]['type'];
            $slots = $this->declaredTokenFqcns[$propName] ?? [];

            // A per-name queue cannot hold the null of an override's token spelled like a class, so each reads a slot.
            $this->properties[$propName]['type'] = in_array(null, $slots, true)
                ? $this->aliasSlottedTokens($type, $slots, $nameMap)
                : TsTypeString::aliasPropertyType($type, $propFqcns, $nameMap, $this->importAliases, $this->siblingImportNames);
        }
    }

    /**
     * Merge every per-property FQCN map — singular and list — into a superset-in-order, prefix-aligned
     * FQCN queue: a property in more than one map gets each map's entries concatenated, so its length can
     * exceed real occurrences; aliasPropertyType() consumes only the matching prefix — never dedupe it.
     *
     * @return array<array-key, list<class-string>>
     */
    protected function mergePropertyFqcnMaps(): array
    {
        /** @var array<array-key, list<class-string>> $merged */
        $merged = [];

        foreach ([$this->propertyEnumFqcns, $this->propertyResourceFqcns, $this->propertyModelFqcns] as $map) {
            foreach ($map as $propName => $propFqcn) {
                $merged[$propName][] = $propFqcn;
            }
        }

        // The inline lists go models first, then resources: InlineObjectResult::sharedNames() relies on that order.
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

    /** @return list<string> */
    protected function transientProperties(): array
    {
        return ['reflectionResource'];
    }

    /**
     * No key keeps a row of enum info: each wrap spells its own `AsEnum<typeof Const>` and queues its const.
     */
    #[Override]
    protected function enumProperties(): array
    {
        return [];
    }

    /**
     * Whether to generate HasEnums value imports: the tolki package is on and some key writes a wrap's const.
     */
    protected function shouldGenerateHasEnums(): bool
    {
        return Config::boolean('ts-publish.enums.use_tolki_package') && $this->propertyInlineEnumResourceFqcns !== [];
    }

    /**
     * Return the unique enum FQCNs whose consts the keys' wraps write, for value-import generation.
     *
     * @return list<class-string>
     */
    protected function enumPropertyFqcns(): array
    {
        return array_values(array_unique(array_merge([], ...array_values($this->propertyInlineEnumResourceFqcns))));
    }

    /**
     * Respell each read token of a declaration's own names in a type by the class its slot names, in order, leaving an
     * override's token, whose slot is null, as spelled.
     *
     * @param  Slots  $slots
     * @param  array<string, string>  $nameMap  FQCN => unaliased type name
     */
    private function aliasSlottedTokens(string $type, array $slots, array $nameMap): string
    {
        $respelled = '';
        $from = 0;

        foreach (TokenSlots::declaredTokens($type, $slots) as $token) {
            if ($token['slot'] === null || ! $token['read']) {
                continue;
            }

            $respelled .= substr($type, $from, $token['offset'] - $from).TsTypeString::aliasPropertyType(
                $token['token'],
                [$token['slot']],
                $nameMap,
                $this->importAliases,
                $this->siblingImportNames,
            );
            $from = $token['offset'] + strlen($token['token']);
        }

        return $respelled.substr($type, $from);
    }
}
