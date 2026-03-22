<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use AbeTwoThree\LaravelTsPublish\Collectors\ModelsCollector;
use AbeTwoThree\LaravelTsPublish\Dtos\TsResourceDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type PropertiesList from TsResourceDto
 * @phpstan-import-type TypesImportMap from TsResourceDto
 * @phpstan-import-type ValuesImportMap from TsResourceDto
 * @phpstan-import-type ResourcePropertyInfo from \AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis
 *
 * @extends CoreTransformer<JsonResource>
 */
class ResourceTransformer extends CoreTransformer
{
    public protected(set) string $resourceName;

    public protected(set) string $description = '';

    public protected(set) string $filePath;

    public protected(set) string $namespacePath;

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

    /** @var array<string, string> custom import path => type name */
    protected array $customImports = [];

    /** @var array<string, bool> property name => optional override */
    protected array $optionalOverrides = [];

    /** @var array<class-string, string> FQCN => TS type alias name (e.g. StatusType) */
    protected array $enumFqcnMap = [];

    /** @var array<class-string, string> FQCN => TS const name (e.g. Status) */
    protected array $enumConstMap = [];

    /** @var array<class-string, string> FQCN => resource interface name */
    protected array $resourceFqcnMap = [];

    /** @var array<string, array{fqcn: class-string, nullable: bool}> property => enum info for EnumResource::make() properties */
    protected array $enumResourceProperties = [];

    /** @var array<class-string, string> FQCN => model interface name */
    protected array $modelFqcnMap = [];

    /** @var array<string, class-string> property name => model FQCN (from bare whenLoaded) */
    protected array $propertyModelFqcns = [];

    /** @var array<string, class-string> property name => resource FQCN (from nested resources) */
    protected array $propertyResourceFqcns = [];

    /** @var array<string, class-string> property name => enum FQCN (from direct enum access) */
    protected array $propertyEnumFqcns = [];

    /** @var array<class-string, string> FQCN => aliased type name */
    protected array $importAliases = [];

    /** @var array<class-string, string> FQCN => aliased const name */
    protected array $constImportAliases = [];

    /** @var array<string, string> property name => TS type override from model's #[TsCasts] */
    protected array $modelTsCastsOverrides = [];

    /** @var array<string, string> property name => import path from model's #[TsCasts] */
    protected array $modelTsCastsImportPaths = [];

    #[Override]
    public function transform(): self
    {
        $this->initReflection()
            ->resolveModelClass()
            ->parseModelTsCastsOverrides()
            ->parseTsResourceCastsOverrides()
            ->runAstAnalysis()
            ->applyOverrides()
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
            filePath: $this->filePath,
            filename: $this->filename(),
            properties: $this->properties,
            typeImports: $this->typeImports,
            valueImports: $this->valueImports,
            modelClass: $this->modelClass,
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

    /**
     * Resolve the backing model class from
     * 1. #[TsResource(model:)] attribute
     * 2. @mixin docblock
     * 3. Convention-based guess (reverse of Laravel's TransformsToResource)
     * 4. #[UseResource] attribute scan on collected models
     */
    protected function resolveModelClass(): self
    {
        // Priority 1: explicit #[TsResource(model:)] attribute
        $tsResourceAttrs = $this->reflectionResource->getAttributes(TsResource::class);

        if ($tsResourceAttrs) {
            $model = $tsResourceAttrs[0]->newInstance()->model;

            if ($model !== null && class_exists($model) && is_a($model, Model::class, true)) {
                $this->modelClass = $model;

                return $this;
            }
        }

        // Priority 2: @mixin in docblock
        $docComment = $this->reflectionResource->getDocComment();

        if ($docComment !== false && preg_match('/@mixin\s+([\w\\\\]+)/', $docComment, $matches)) {
            $mixinClass = ltrim($matches[1], '\\');

            // If already a FQCN (unreachable when Pint enforces fully_qualified_strict_types)
            // @codeCoverageIgnoreStart
            if (class_exists($mixinClass) && is_a($mixinClass, Model::class, true)) {
                $this->modelClass = $mixinClass;

                return $this;
            }
            // @codeCoverageIgnoreEnd

            // Resolve from use statements in the source file
            $resolved = $this->resolveClassFromUseStatements($mixinClass);

            if ($resolved !== null) {
                $this->modelClass = $resolved;

                return $this;
            }

            // Try to resolve relative to the resource's namespace
            // @codeCoverageIgnoreStart
            $resourceNamespace = $this->reflectionResource->getNamespaceName();
            $fullClass = $resourceNamespace.'\\'.$mixinClass;

            if (class_exists($fullClass) && is_a($fullClass, Model::class, true)) {
                $this->modelClass = $fullClass;

                return $this;
            }
            // @codeCoverageIgnoreEnd
        }

        // Priority 3: convention-based guess (reverse of Laravel's TransformsToResource)
        $guessed = $this->guessModelFromConvention();

        if ($guessed !== null) {
            $this->modelClass = $guessed;

            return $this;
        }

        // Priority 4: scan models for #[UseResource] attribute pointing to this resource
        $useResourceModel = $this->guessModelFromUseResourceAttribute();

        if ($useResourceModel !== null) {
            $this->modelClass = $useResourceModel;

            return $this;
        }

        return $this;
    }

    /**
     * Resolve a short class name from the resource file's use statements.
     *
     * @return class-string<Model>|null
     */
    /**
     * @return class-string<Model>|null
     */
    protected function resolveClassFromUseStatements(string $shortName): ?string
    {
        $filePath = (string) $this->reflectionResource->getFileName();
        $source = (string) file_get_contents($filePath);

        if (preg_match_all('/^use\s+([\w\\\\]+\\\\'.preg_quote($shortName, '/').')\s*;/m', $source, $matches)) {
            $fqcn = $matches[1][0];

            if (class_exists($fqcn) && is_a($fqcn, Model::class, true)) {
                return $fqcn;
            }
        }

        return null; // @codeCoverageIgnore
    }

    /**
     * Guess the backing model by reversing Laravel's resource naming convention.
     *
     * Given `App\Http\Resources\{Sub}\{Name}Resource`, tries `App\Models\{Sub}\{Name}`.
     *
     * @return class-string<Model>|null
     */
    protected function guessModelFromConvention(): ?string
    {
        $resourceFqcn = $this->reflectionResource->getName();

        if (! Str::contains($resourceFqcn, '\\Http\\Resources\\')) {
            return null;
        }

        $beforeResources = Str::before($resourceFqcn, '\\Http\\Resources\\');
        $afterResources = Str::after($resourceFqcn, '\\Http\\Resources\\');

        $basename = class_basename($resourceFqcn);

        $relativeNamespace = Str::contains($afterResources, '\\')
            ? Str::before($afterResources, '\\'.$basename)
            : '';

        $prefix = $beforeResources.'\\Models\\'
            .(strlen($relativeNamespace) > 0 ? $relativeNamespace.'\\' : '');

        // Try without "Resource" suffix first (most common convention)
        $withoutSuffix = Str::endsWith($basename, 'Resource')
            ? Str::beforeLast($basename, 'Resource')
            : null;

        if ($withoutSuffix !== null && $withoutSuffix !== '') {
            $candidate = $prefix.$withoutSuffix;

            if (class_exists($candidate) && is_a($candidate, Model::class, true)) {
                return $candidate;
            }
        }

        // Try the class name as-is (e.g., App\Http\Resources\User → App\Models\User)
        $candidate = $prefix.$basename;

        if (class_exists($candidate) && is_a($candidate, Model::class, true)) {
            return $candidate;
        }

        return null;
    }

    /**
     * Scan collected models for a #[UseResource] attribute pointing to this resource.
     *
     * @return class-string<Model>|null
     */
    protected function guessModelFromUseResourceAttribute(): ?string
    {
        /** @var ModelsCollector $collector */
        $collector = resolve(config()->string('ts-publish.model_collector_class'));

        foreach ($collector->collect() as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            $attrs = $reflection->getAttributes('Illuminate\\Database\\Eloquent\\Attributes\\UseResource');

            if ($attrs !== [] && $attrs[0]->newInstance()->class === $this->findable) {
                return $modelClass;
            }
        }

        return null;
    }

    /**
     * Parse #[TsCasts] attributes from the backing model for type overrides.
     */
    protected function parseModelTsCastsOverrides(): self
    {
        if ($this->modelClass === null || ! class_exists($this->modelClass)) {
            return $this;
        }

        $reflection = new ReflectionClass($this->modelClass);

        $classOverrides = [];
        $propertyOverrides = [];
        $methodOverrides = [];

        foreach ($reflection->getAttributes(TsCasts::class) as $attr) {
            $classOverrides = array_merge($classOverrides, $attr->newInstance()->types);
        }

        if ($reflection->hasProperty('casts')) {
            foreach ($reflection->getProperty('casts')->getAttributes(TsCasts::class) as $attr) {
                $propertyOverrides = array_merge($propertyOverrides, $attr->newInstance()->types);
            }
        }

        if ($reflection->hasMethod('casts')) {
            foreach ($reflection->getMethod('casts')->getAttributes(TsCasts::class) as $attr) {
                $methodOverrides = array_merge($methodOverrides, $attr->newInstance()->types);
            }
        }

        $merged = array_merge($classOverrides, $propertyOverrides, $methodOverrides);

        foreach ($merged as $column => $value) {
            if (is_array($value)) {
                /** @var array{type: string, import: string} $value */
                $this->modelTsCastsOverrides[$column] = $value['type'];
                $this->modelTsCastsImportPaths[$column] = $value['import'];
            } else {
                $this->modelTsCastsOverrides[$column] = $value;
            }
        }

        return $this;
    }

    /**
     * Parse #[TsResourceCasts] attributes for type overrides.
     */
    protected function parseTsResourceCastsOverrides(): self
    {
        foreach ($this->reflectionResource->getAttributes(TsResourceCasts::class) as $attr) {
            $instance = $attr->newInstance();

            foreach ($instance->types as $property => $value) {
                if (is_array($value)) {
                    $this->tsTypeOverrides[$property] = $value['type'];

                    if (isset($value['import'])) {
                        $this->customImports[$value['import']] = $value['type'];
                    }

                    if (isset($value['optional'])) {
                        $this->optionalOverrides[$property] = $value['optional'];
                    }
                } else {
                    $this->tsTypeOverrides[$property] = $value;
                }
            }
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

        // Convert ResourcePropertyInfo list into PropertiesList map
        foreach ($analysis->properties as $prop) {
            $this->properties[$prop['name']] = [
                'type' => $prop['type'],
                'optional' => $prop['optional'],
                'description' => $prop['description'],
            ];
        }

        // Populate enum tracking maps from EnumResource::make() properties
        foreach ($analysis->enumResources as $propName => $fqcn) {
            $tsInfo = LaravelTsPublish::phpToTypeScriptType($fqcn);
            $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
            $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
            $nullable = str_contains($this->properties[$propName]['type'] ?? '', 'null');
            $this->enumResourceProperties[$propName] = ['fqcn' => $fqcn, 'nullable' => $nullable];
            $this->propertyEnumFqcns[$propName] = $fqcn;
        }

        // Populate enum tracking maps from direct $this->prop enum access
        foreach ($analysis->directEnumFqcns as $propName => $fqcn) {
            if (! isset($this->enumFqcnMap[$fqcn])) {
                $tsInfo = LaravelTsPublish::phpToTypeScriptType($fqcn);
                $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
                $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
            }
            $this->propertyEnumFqcns[$propName] = $fqcn;
        }

        // Populate nested resource tracking map (skip self-references)
        foreach ($analysis->nestedResources as $propName => $fqcn) {
            if ($fqcn !== $this->findable) {
                $this->resourceFqcnMap[$fqcn] = class_basename($fqcn);
                $this->propertyResourceFqcns[$propName] = $fqcn;
            }
        }

        // Populate model tracking map from bare whenLoaded relations
        foreach ($analysis->modelFqcns as $propName => $fqcn) {
            $this->modelFqcnMap[$fqcn] = class_basename($fqcn);
            $this->propertyModelFqcns[$propName] = $fqcn;
        }

        // Merge custom imports from trait method #[TsResourceCasts] attributes
        foreach ($analysis->customImports as $importPath => $typeName) {
            $this->customImports[$importPath] = $typeName;
        }

        return $this;
    }

    /**
     * Apply model #[TsCasts] then #[TsResourceCasts] overrides on top of AST-inferred properties.
     */
    protected function applyOverrides(): self
    {
        // Apply model TsCasts overrides (only for properties already in toArray, not overridden by TsResourceCasts)
        foreach ($this->modelTsCastsOverrides as $property => $type) {
            if (isset($this->properties[$property]) && ! isset($this->tsTypeOverrides[$property])) {
                $this->properties[$property]['type'] = $type;

                if (isset($this->modelTsCastsImportPaths[$property])) {
                    $this->customImports[$this->modelTsCastsImportPaths[$property]] = $type;
                }
            }
        }

        // Apply TsResourceCasts overrides (highest priority, can add new properties)
        foreach ($this->tsTypeOverrides as $property => $type) {
            if (isset($this->properties[$property])) {
                $this->properties[$property]['type'] = $type;
            } else {
                // Override adds a property not found in AST
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
        $isModular = config()->boolean('ts-publish.modular_publishing');
        $hasEnums = $this->shouldGenerateHasEnums();

        if ($isModular) {
            foreach ($this->enumFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $typeImports[$importPath][] = $this->formatImportName($fqcn, $typeName);
            }

            if ($hasEnums) {
                foreach ($this->enumResourcePropertyFqcns() as $fqcn) {
                    $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                    $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                    $valueImports[$importPath][] = $this->formatConstImportName($fqcn);
                }
            }

            foreach ($this->resourceFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $typeImports[$importPath][] = $this->formatImportName($fqcn, $typeName);
            }

            foreach ($this->modelFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $typeImports[$importPath][] = $this->formatImportName($fqcn, $typeName);
            }
        } else {
            $enumTypeImports = [];
            foreach ($this->enumFqcnMap as $fqcn => $typeName) {
                $enumTypeImports[] = $this->formatImportName($fqcn, $typeName);
            }
            $enumTypeImports = array_values(array_unique($enumTypeImports));

            if ($enumTypeImports !== []) {
                sort($enumTypeImports);
                $typeImports['../enums'] = $enumTypeImports;
            }

            if ($hasEnums) {
                $enumValueImports = [];
                foreach ($this->enumResourcePropertyFqcns() as $fqcn) {
                    $enumValueImports[] = $this->formatConstImportName($fqcn);
                }
                $enumValueImports = array_values(array_unique($enumValueImports));

                if ($enumValueImports !== []) {
                    sort($enumValueImports);
                    $valueImports['../enums'] = $enumValueImports;
                }
            }

            $resourceImports = [];
            foreach ($this->resourceFqcnMap as $fqcn => $typeName) {
                $resourceImports[] = $this->formatImportName($fqcn, $typeName);
            }
            $resourceImports = array_values(array_unique($resourceImports));

            if ($resourceImports !== []) {
                sort($resourceImports);
                $typeImports['./'] = $resourceImports;
            }

            $modelImports = [];
            foreach ($this->modelFqcnMap as $fqcn => $typeName) {
                $modelImports[] = $this->formatImportName($fqcn, $typeName);
            }
            $modelImports = array_values(array_unique($modelImports));

            if ($modelImports !== []) {
                sort($modelImports);
                $typeImports['../models'] = $modelImports;
            }
        }

        // Merge custom imports from TsResourceCasts
        foreach ($this->customImports as $importPath => $typeName) {
            $importableTypes = LaravelTsPublish::extractImportableTypes($typeName);

            if ($importableTypes !== []) {
                $existing = $typeImports[$importPath] ?? [];
                $typeImports[$importPath] = array_values(array_unique([...$existing, ...$importableTypes]));
            }
        }

        // Deduplicate and sort per path
        foreach ($typeImports as $path => $types) {
            $uniqueTypes = array_values(array_unique($types));
            sort($uniqueTypes);
            $typeImports[$path] = $uniqueTypes;
        }

        foreach ($valueImports as $path => $names) {
            $uniqueNames = array_values(array_unique($names));
            sort($uniqueNames);
            $valueImports[$path] = $uniqueNames;
        }

        $this->typeImports = LaravelTsPublish::sortImportPaths($typeImports);
        $this->valueImports = LaravelTsPublish::sortImportPaths($valueImports);

        return $this;
    }

    /**
     * Rewrite EnumResource::make() property types to AsEnum<typeof Const> when tolki package is enabled.
     * When disabled, leave types as StatusType (they get type imports instead).
     */
    protected function rewriteEnumResourceTypes(): self
    {
        if (! config()->boolean('ts-publish.enums_use_tolki_package') || $this->enumResourceProperties === []) {
            return $this;
        }

        foreach ($this->enumResourceProperties as $propName => $info) {
            if (! isset($this->properties[$propName])) {
                continue; // @codeCoverageIgnore
            }

            $constName = $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']];
            $type = 'AsEnum<typeof '.$constName.'>';

            if ($info['nullable']) {
                $type .= ' | null'; // @codeCoverageIgnore
            }

            $this->properties[$propName] = [
                ...$this->properties[$propName],
                'type' => $type,
            ];

            // Remove from type import map unless this FQCN is also used for direct property access
            $usedForDirectAccess = false;

            foreach ($this->propertyEnumFqcns as $prop => $propFqcn) {
                if ($propFqcn === $info['fqcn'] && ! isset($this->enumResourceProperties[$prop])) {
                    $usedForDirectAccess = true;

                    break;
                }
            }

            if (! $usedForDirectAccess) {
                unset($this->enumFqcnMap[$info['fqcn']]);
            }
        }

        return $this;
    }

    /**
     * Detect import name collisions across all FQCN maps and assign aliases.
     */
    protected function resolveImportConflicts(): self
    {
        /** @var array<string, list<array{fqcn: string, kind: 'enum'|'resource'|'model'}>> $reverseMap */
        $reverseMap = [];

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $reverseMap[$typeName][] = ['fqcn' => $fqcn, 'kind' => 'enum'];
        }

        foreach ($this->resourceFqcnMap as $fqcn => $typeName) {
            $reverseMap[$typeName][] = ['fqcn' => $fqcn, 'kind' => 'resource'];
        }

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            $reverseMap[$typeName][] = ['fqcn' => $fqcn, 'kind' => 'model'];
        }

        foreach ($reverseMap as $typeName => $entries) {
            // Also conflict if the type name matches this resource's own name
            $needsAlias = count($entries) > 1 || $typeName === $this->resourceName;

            if (! $needsAlias) {
                continue;
            }

            foreach ($entries as $entry) {
                /** @var class-string $fqcn */
                $fqcn = $entry['fqcn'];
                $originalName = match ($entry['kind']) {
                    'enum' => $this->enumFqcnMap[$fqcn],
                    'resource' => $this->resourceFqcnMap[$fqcn],
                    'model' => $this->modelFqcnMap[$fqcn],
                };

                $prefix = $this->computeNamespacePrefix($fqcn);
                $alias = $prefix.$originalName;

                $this->importAliases[$fqcn] = $alias;

                if ($entry['kind'] === 'enum' && isset($this->enumConstMap[$fqcn])) {
                    $this->constImportAliases[$fqcn] = $prefix.$this->enumConstMap[$fqcn];
                }
            }
        }

        if ($this->importAliases !== []) {
            $this->rewriteTypeReferences();
        }

        return $this;
    }

    /**
     * Compute a distinguishing namespace prefix for alias generation.
     */
    protected function computeNamespacePrefix(string $fqcn): string
    {
        $namespace = Str::beforeLast($fqcn, '\\');

        $prefix = config()->string('ts-publish.namespace_strip_prefix', '');

        if ($prefix !== '' && str_starts_with($namespace, $prefix)) {
            $namespace = substr($namespace, strlen($prefix));
        }

        $segments = array_filter(explode('\\', $namespace));
        $skip = ['Models', 'Enums', 'Http', 'Resources', 'App'];

        foreach (array_reverse($segments) as $segment) {
            if (! in_array($segment, $skip, true)) {
                return Str::studly($segment);
            }
        }

        $first = reset($segments);

        return $first !== false ? Str::studly($first) : '';
    }

    /**
     * Rewrite property type references to use aliased names.
     */
    protected function rewriteTypeReferences(): void
    {
        foreach ($this->importAliases as $fqcn => $alias) {
            $originalName = $this->enumFqcnMap[$fqcn]
                ?? $this->resourceFqcnMap[$fqcn]
                ?? $this->modelFqcnMap[$fqcn]
                ?? null;

            if ($originalName === null || $originalName === $alias) {
                continue; // @codeCoverageIgnore
            }

            $pattern = '/(?<![A-Za-z0-9_$])'.preg_quote($originalName, '/').'(?![A-Za-z0-9_$])/';

            // Use property→FQCN tracking maps for precise replacement
            $targetProperties = [];

            foreach ($this->propertyEnumFqcns as $propName => $propFqcn) {
                if ($propFqcn === $fqcn) {
                    $targetProperties[] = $propName;
                }
            }

            foreach ($this->propertyResourceFqcns as $propName => $propFqcn) {
                if ($propFqcn === $fqcn) {
                    $targetProperties[] = $propName;
                }
            }

            foreach ($this->propertyModelFqcns as $propName => $propFqcn) {
                if ($propFqcn === $fqcn) {
                    $targetProperties[] = $propName;
                }
            }

            foreach ($targetProperties as $propName) {
                if (! isset($this->properties[$propName])) {
                    continue; // @codeCoverageIgnore
                }
                $this->properties[$propName]['type'] = preg_replace(
                    $pattern,
                    $alias,
                    $this->properties[$propName]['type'],
                ) ?? $this->properties[$propName]['type'];
            }
        }
    }

    /**
     * Format an import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatImportName(string $fqcn, string $typeName): string
    {
        $alias = $this->importAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $typeName) {
            return $typeName.' as '.$alias;
        }

        return $typeName;
    }

    /**
     * Format a const import name, applying "OriginalName as Alias" syntax when aliased.
     */
    protected function formatConstImportName(string $fqcn): string
    {
        $constName = $this->enumConstMap[$fqcn];
        $alias = $this->constImportAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $constName) {
            return $constName.' as '.$alias;
        }

        return $constName;
    }

    protected function shouldGenerateHasEnums(): bool
    {
        return config()->boolean('ts-publish.enums_use_tolki_package')
            && $this->enumResourceProperties !== [];
    }

    /** @return list<class-string> */
    protected function enumResourcePropertyFqcns(): array
    {
        return array_values(array_unique(array_column($this->enumResourceProperties, 'fqcn')));
    }
}
