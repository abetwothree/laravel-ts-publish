<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAstAnalyzer;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
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
            ->rewriteEnumResourceTypes()
            ->buildImports();

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
     * Resolve the backing model class from #[TsResource(model:)] or @mixin docblock.
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
        }

        // Populate enum tracking maps from direct $this->prop enum access
        foreach ($analysis->directEnumFqcns as $fqcn) {
            if (! isset($this->enumFqcnMap[$fqcn])) {
                $tsInfo = LaravelTsPublish::phpToTypeScriptType($fqcn);
                $this->enumFqcnMap[$fqcn] = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
                $this->enumConstMap[$fqcn] = $tsInfo['enums'][0] ?? class_basename($fqcn);
            }
        }

        // Populate nested resource tracking map (skip self-references)
        foreach ($analysis->nestedResources as $fqcn) {
            if ($fqcn !== $this->findable) {
                $this->resourceFqcnMap[$fqcn] = class_basename($fqcn);
            }
        }

        // Populate model tracking map from bare whenLoaded relations
        foreach ($analysis->modelFqcns as $fqcn) {
            $this->modelFqcnMap[$fqcn] = class_basename($fqcn);
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
    protected function buildImports(): self
    {
        $typeImports = [];
        $valueImports = [];
        $isModular = config()->boolean('ts-publish.modular_publishing');
        $hasEnums = $this->shouldGenerateHasEnums();

        if ($isModular) {
            foreach ($this->enumFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $typeImports[$importPath][] = $typeName;
            }

            if ($hasEnums) {
                foreach ($this->enumResourcePropertyFqcns() as $fqcn) {
                    $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                    $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                    $valueImports[$importPath][] = $this->enumConstMap[$fqcn];
                }
            }

            foreach ($this->resourceFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $typeImports[$importPath][] = $typeName;
            }

            foreach ($this->modelFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $typeImports[$importPath][] = $typeName;
            }
        } else {
            $enumTypeImports = array_values(array_unique(array_values($this->enumFqcnMap)));

            if ($enumTypeImports !== []) {
                sort($enumTypeImports);
                $typeImports['../enums'] = $enumTypeImports;
            }

            if ($hasEnums) {
                $enumValueImports = [];
                foreach ($this->enumResourcePropertyFqcns() as $fqcn) {
                    $enumValueImports[] = $this->enumConstMap[$fqcn];
                }
                $enumValueImports = array_values(array_unique($enumValueImports));

                if ($enumValueImports !== []) {
                    sort($enumValueImports);
                    $valueImports['../enums'] = $enumValueImports;
                }
            }

            $resourceImports = array_values(array_unique(array_values($this->resourceFqcnMap)));

            if ($resourceImports !== []) {
                sort($resourceImports);
                $typeImports['./'] = $resourceImports;
            }

            $modelImports = array_values(array_unique(array_values($this->modelFqcnMap)));

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

            $constName = $this->enumConstMap[$info['fqcn']];
            $type = 'AsEnum<typeof '.$constName.'>';

            if ($info['nullable']) {
                $type .= ' | null'; // @codeCoverageIgnore
            }

            $this->properties[$propName] = [
                ...$this->properties[$propName],
                'type' => $type,
            ];

            // Remove from type import map — these FQCNs get value imports instead
            unset($this->enumFqcnMap[$info['fqcn']]);
        }

        return $this;
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
