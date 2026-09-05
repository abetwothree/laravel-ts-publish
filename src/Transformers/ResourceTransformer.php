<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\ModelClassResolver;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ParsesTsExtends;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\TracksEnumImports;
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

    /** @var array<string, bool> property name => optional override */
    protected array $optionalOverrides = [];

    /** @var array<class-string, string> FQCN => resource interface name */
    protected array $resourceFqcnMap = [];

    /** @var array<string, array{fqcn: class-string, nullable: bool, isCollection: bool}> property => enum info for EnumResource::make()/::collection() properties */
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
            ->resolveMultiClassAccessorFqcns()
            ->resolveMultiEnumAccessorFqcns()
            ->resolveImportConflicts()
            ->rewriteEnumResourceTypes()
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
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);

        $tsResourceAttrs = $this->reflectionResource->getAttributes(TsResource::class);

        if ($tsResourceAttrs) {
            $tsResourceInstance = $tsResourceAttrs[0]->newInstance();
            $this->resourceName = $tsResourceInstance->name ?? $this->reflectionResource->getShortName();
            $this->description = $tsResourceInstance->description !== ''
                ? $tsResourceInstance->description
                : LaravelTsPublish::parseDocBlockDescription($this->reflectionResource->getDocComment());
        } else {
            $this->resourceName = $this->reflectionResource->getShortName();
            $this->description = LaravelTsPublish::parseDocBlockDescription($this->reflectionResource->getDocComment());
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

        foreach ($result['importPaths'] as $property => $importPath) {
            $type = $result['overrides'][$property] ?? null;

            if ($type !== null) {
                foreach (LaravelTsPublish::extractImportableTypes($type) as $importName) {
                    $this->customImports[$importPath][] = $importName;
                }
            }
        }

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

        // ResourceCollection subclasses with $wrap = null emit an alias, not an interface.
        if ($analysis->flatTypeAlias !== null) {
            $this->typeAlias = $analysis->flatTypeAlias;

            if ($analysis->flatTypeAliasFqcn !== null && $analysis->flatTypeAliasFqcn !== $this->findable) {
                $this->resourceFqcnMap[$analysis->flatTypeAliasFqcn] = LaravelTsPublish::resourceTypeName($analysis->flatTypeAliasFqcn);
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
            $this->enumResourceProperties[$propName] = ['fqcn' => $fqcn, 'nullable' => $nullable, 'isCollection' => $isCollection];
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
                $this->resourceFqcnMap[$fqcn] = LaravelTsPublish::resourceTypeName($fqcn);
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

        foreach ($analysis->customImports as $importPath => $types) {
            $this->customImports[$importPath] = [...($this->customImports[$importPath] ?? []), ...$types];
        }

        return $this;
    }

    /**
     * Apply model #[TsCasts] then resource #[TsCasts] overrides on top of AST-inferred properties.
     */
    protected function applyOverrides(): self
    {
        foreach ($this->modelTsCastsOverrides as $property => $type) {
            if (isset($this->properties[$property]) && ! isset($this->tsTypeOverrides[$property])) {
                $this->properties[$property]['type'] = $type;

                if (isset($this->modelTsCastsImportPaths[$property])) {
                    foreach (LaravelTsPublish::extractImportableTypes($type) as $importName) {
                        $this->customImports[$this->modelTsCastsImportPaths[$property]][] = $importName;
                    }
                }

                if (isset($this->modelTsCastsOptionalOverrides[$property])) {
                    $this->properties[$property]['optional'] = $this->modelTsCastsOptionalOverrides[$property];
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

        foreach ($this->optionalOverrides as $property => $optional) {
            if (isset($this->properties[$property])) {
                $this->properties[$property]['optional'] = $optional;
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
                // tell the arms apart here — synthesize the union explicitly instead.
                $wrappedTypeName = 'AsEnum<typeof '.$constName.'>';

                // EnumResource::make() is always scalar; the direct arm's own shape decides the array suffix.
                // Assumes the wrap arm is never EnumResource::collection() — not true in general, and known.
                $directTypeName = $info['isCollection'] ? $enumTypeName.'[]' : $enumTypeName;

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
                $type = $this->substituteEnumResourceType(
                    $this->properties[$propName]['type'],
                    $searchTypeName,
                    'AsEnum<typeof '.$constName.'>',
                );
            }

            $this->properties[$propName] = [
                ...$this->properties[$propName],
                'type' => $type,
            ];

            // The type import survives only if some property still reads this enum directly.
            $usedForDirectAccess = $isMixed;

            if (! $usedForDirectAccess) {
                foreach ($this->directEnumProperties as $prop => $propFqcn) {
                    if ($propFqcn === $info['fqcn']) {
                        $usedForDirectAccess = true;

                        break;
                    }
                }
            }

            if (! $usedForDirectAccess) {
                foreach ($this->propertyEnumFqcns as $prop => $propFqcn) {
                    if ($propFqcn === $info['fqcn'] && ! isset($this->enumResourceProperties[$prop])) {
                        $usedForDirectAccess = true;

                        break;
                    }
                }
            }

            // A bare enum read nested inside an inline array (e.g. ['role' => $member->role])
            // is the semantically correct signal to check here, checked explicitly rather than
            // relying on dispatchFqcnResults()'s FQCN-keyed directEnumFqcns entries above.
            if (! $usedForDirectAccess) {
                foreach ($this->propertyInlineEnumFqcns as $propFqcns) {
                    if (in_array($info['fqcn'], $propFqcns, true)) {
                        $usedForDirectAccess = true;

                        break;
                    }
                }
            }

            if (! $usedForDirectAccess) {
                unset($this->enumFqcnMap[$info['fqcn']]);
            }
        }

        // Ternaries whose non-null branches each wrap a different enum: replace tokens positionally.
        foreach ($this->multiEnumResourceProperties as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $tokens = explode(' | ', $this->properties[$propName]['type']);
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
                    $stillNeeded = false;

                    foreach ($this->directEnumProperties as $propFqcn) {
                        if ($propFqcn === $fqcn) {
                            $stillNeeded = true;

                            break;
                        }
                    }

                    if (! $stillNeeded) {
                        foreach ($this->propertyEnumFqcns as $prop => $propFqcn) {
                            if ($propFqcn === $fqcn && ! isset($this->enumResourceProperties[$prop])) {
                                $stillNeeded = true;

                                break;
                            }
                        }
                    }

                    if (! $stillNeeded) {
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

        // analyzeInlineArray() already substituted each inline wrap's bare const name into
        // 'AsEnum<typeof {bare}>'; rewriteTypeReferences() can't alias it — $nameMap excludes
        // enumConstMap. Two inline members can share one bare name, so alias by FQCN order.
        foreach ($this->propertyInlineEnumResourceFqcns as $propName => $fqcns) {
            if (! isset($this->properties[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $this->properties[$propName]['type'] = LaravelTsPublish::aliasPropertyType(
                $this->properties[$propName]['type'],
                $fqcns,
                $this->enumConstMap,
                $this->constImportAliases,
            );
        }

        return $this;
    }

    /**
     * Replace every word-boundary-safe occurrence of a bare enum type name with its AsEnum wrap.
     *
     * Preserves everything else in the analyzer's type string — unions, Record arms, extra default
     * arms — since only the wrapped enum's own token changes, not the shape around it.
     */
    protected function substituteEnumResourceType(string $typeStr, string $bareTypeName, string $asEnumType): string
    {
        $pattern = '/(?<![A-Za-z0-9_$.])'.preg_quote($bareTypeName, '/').'(?![A-Za-z0-9_$])/';

        return preg_replace($pattern, $asEnumType, $typeStr) ?? $typeStr;
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
            if (isset($this->propertyEnumFqcnsList[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $tsInfo = $resolver->resolveAttribute($this->modelClass, $propName);

            if (count($tsInfo['enumFqcns']) < 2) {
                continue;
            }

            $this->propertyEnumFqcnsList[$propName] = $tsInfo['enumFqcns'];

            foreach ($tsInfo['enumFqcns'] as $i => $fqcn) {
                /** @var class-string $fqcn */
                if (! isset($this->enumFqcnMap[$fqcn])) {
                    $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][$i] ?? class_basename($fqcn).'Type';
                    $this->enumConstMap[$fqcn] = $tsInfo['enums'][$i] ?? class_basename($fqcn);
                }
            }
        }

        return $this;
    }

    /**
     * Register both class FQCNs of accessors typed Attribute<ClassA|ClassB, never> so they can be aliased,
     * and the #[TsType(import:)] paths of model attributes, which no analysis path carries into a resource.
     */
    protected function resolveMultiClassAccessorFqcns(): self
    {
        if ($this->modelClass === null) {
            return $this;
        }

        $resolver = resolve(ModelAttributeResolver::class);
        $modelClass = $this->modelClass;

        foreach (array_keys($this->properties) as $propName) {
            // Skip when inline analysis already owns this property's FQCNs — letting both maps populate here
            // would double the merged queue and break its prefix alignment with real occurrences.
            if (isset($this->propertyModelFqcns[$propName]) || isset($this->propertyInlineModelFqcns[$propName])) {
                continue;
            }

            $tsInfo = $resolver->resolveAttribute($modelClass, $propName);

            $this->registerModelAttributeCustomImports($propName, $tsInfo['customImports']);

            if ($tsInfo['classFqcns'] === []) {
                continue;
            }

            $this->propertyModelFqcnsList[$propName] = $tsInfo['classFqcns'];

            foreach ($tsInfo['classFqcns'] as $i => $fqcn) {
                /** @var class-string $fqcn */
                if (! isset($this->modelFqcnMap[$fqcn])) {
                    $this->modelFqcnMap[$fqcn] = $tsInfo['classes'][$i]; // @codeCoverageIgnore
                }
            }
        }

        return $this;
    }

    /**
     * Import a model attribute's #[TsType(import:)] names, but only those the emitted property still uses.
     *
     * A resource may override the model's type, and an unused import is a tsc error under noUnusedLocals.
     *
     * @param  array<string, list<string>>  $imports
     */
    protected function registerModelAttributeCustomImports(string $propName, array $imports): void
    {
        foreach ($imports as $path => $names) {
            foreach ($names as $name) {
                if (str_contains($this->properties[$propName]['type'] ?? '', $name)) {
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

        // A sibling registry resolves const names independently rather than string-slicing the type
        // alias, which breaks on a numeric tiebreak suffix. The two registries can't see each other, so
        // a const name equal to another enum's type name still collides — see the docs' known limitation.
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

        $this->applyResolvedImportNames(
            $registry->resolve(),
            $this->enumFqcnMap + $this->resourceFqcnMap + $this->modelFqcnMap,
            $constRegistry->resolve(),
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

            $this->properties[$propName]['type'] = LaravelTsPublish::aliasPropertyType(
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

        foreach ([
            $this->propertyModelFqcnsList,
            $this->propertyEnumFqcnsList,
            $this->propertyInlineEnumFqcns,
            $this->propertyInlineModelFqcns,
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
            $ns = str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn));

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
                $ns = str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn));
                $map[$alias] = $ns.'.'.$this->enumFqcnMap[$fqcn];
            } elseif (isset($this->resourceFqcnMap[$fqcn])) {
                $ns = str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn));
                $map[$alias] = $ns.'.'.$this->resourceFqcnMap[$fqcn];
            } elseif (isset($this->modelFqcnMap[$fqcn])) {
                $ns = str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn));
                $map[$alias] = $ns.'.'.$this->modelFqcnMap[$fqcn];
            }
        }

        return $map;
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
     * Whether to generate HasEnums value imports, also counting enums wrapped inside inline object types.
     */
    protected function shouldGenerateHasEnums(): bool
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return false;
        }

        return $this->enumProperties() !== [] || $this->propertyInlineEnumResourceFqcns !== [];
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
