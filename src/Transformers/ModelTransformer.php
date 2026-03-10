<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 *
 * @phpstan-type AttributeInfo = array{name: string, type: string, cast: string|null, nullable: bool}
 * @phpstan-type RelationInfo = array{name: string, type: string, related: class-string<Model>}
 * @phpstan-type ModelInspectData = array{
 *     "class": class-string<Model>,
 *     "database": string,
 *     "table": string,
 *     "policy": class-string|null,
 *     "attributes": Collection<int, AttributeInfo>,
 *     "relations": Collection<int, RelationInfo>,
 *     "events": Collection<int, array{event: string, class: class-string}>,
 *     "observers": Collection<int, array{event: string, observer: class-string}>,
 *     "collection": class-string<Collection<int, Model>>,
 *     "builder": class-string<Builder<Model>>,
 *     "resource": class-string<JsonResource>|null
 * }
 * @phpstan-type DbColumns = list<string>
 * @phpstan-type ColumnsList = array<string, string>
 * @phpstan-type MutatorsList = array<string, string>
 * @phpstan-type RelationsList = array<string, string>
 * @phpstan-type TsTypeOverrides = array<string, string>
 * @phpstan-type ResolvedImportMap = array<string, list<string>>
 * @phpstan-type ModelData = array{
 *    modelName: string,
 *    filePath: string,
 *    columns: ColumnsList,
 *    resolvedImports: ResolvedImportMap,
 *    mutators: MutatorsList,
 *    relations: RelationsList,
 * }
 *
 * @extends CoreTransformer<Model>
 */
class ModelTransformer extends CoreTransformer
{
    public protected(set) string $modelName;

    public protected(set) string $filePath;

    public protected(set) string $namespacePath;

    public protected(set) Model $modelInstance;

    /** @var ReflectionClass<Model> */
    public protected(set) ReflectionClass $reflectionModel;

    /** @var DbColumns */
    public protected(set) array $dbColumns = [];

    /** @var ModelInspectData */
    public protected(set) array $modelInspect;

    /** @var ColumnsList */
    public protected(set) array $columns = [];

    /** @var MutatorsList */
    public protected(set) array $mutators = [];

    /** @var RelationsList */
    public protected(set) array $relations = [];

    /** @var TsTypeOverrides */
    public protected(set) array $tsTypeOverrides = [];

    /** @var array<string, string> FQCN => TypeScript type alias name */
    protected array $enumFqcnMap = [];

    /** @var array<string, string> FQCN => TypeScript short name */
    protected array $modelFqcnMap = [];

    /** @var array<string, list<string>> FQCN => list of relation method names that reference it */
    protected array $modelFqcnRelations = [];

    /** @var array<string, string> FQCN => aliased TypeScript name (only for conflicting imports) */
    protected array $importAliases = [];

    /** @var array<string, list<string>> */
    protected array $customImports = [];

    #[Override]
    public function transform(): self
    {
        $this->initInstance()
            ->parseTsTypeOverrides()
            ->transformColumns()
            ->transformMutators()
            ->transformRelations()
            ->resolveImportConflicts();

        return $this;
    }

    /**
     * Get the transformed data
     *
     * @return ModelData
     */
    #[Override]
    public function data(): array
    {
        $resolvedImports = $this->buildResolvedImports();

        return [
            'modelName' => $this->modelName,
            'filePath' => $this->filePath,
            'columns' => $this->columns,
            'mutators' => $this->mutators,
            'relations' => $this->relations,
            'resolvedImports' => $resolvedImports,
        ];
    }

    #[Override]
    public function filename(): string
    {
        return Str::kebab($this->modelName);
    }

    protected function initInstance(): self
    {
        /** @var Model $modelInstance */
        $modelInstance = resolve($this->findable);
        $this->modelInstance = $modelInstance;
        $this->dbColumns = $this->modelInstance->getConnection()->getSchemaBuilder()->getColumnListing($this->modelInstance->getTable());
        $this->modelInspect = resolve(ModelInspector::class)->inspect($this->findable);
        $this->reflectionModel = new ReflectionClass($this->findable);
        $this->modelName = $this->reflectionModel->getShortName();
        $this->filePath = $this->resolveRelativePath((string) $this->reflectionModel->getFileName());
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);

        return $this;
    }

    protected function parseTsTypeOverrides(): self
    {
        $classOverrides = [];
        $propertyOverrides = [];
        $methodOverrides = [];

        // Class-level (Laravel 13+ style, or when there is no $casts property/method)
        foreach ($this->reflectionModel->getAttributes(TsCasts::class) as $attr) {
            $instance = $attr->newInstance();
            $classOverrides = array_merge($classOverrides, $instance->types);
        }

        // $casts property (older style)
        if ($this->reflectionModel->hasProperty('casts')) {
            foreach ($this->reflectionModel->getProperty('casts')->getAttributes(TsCasts::class) as $attr) {
                $instance = $attr->newInstance();
                $propertyOverrides = array_merge($propertyOverrides, $instance->types);
            }
        }

        // casts() method (Laravel 9+ style)
        if ($this->reflectionModel->hasMethod('casts')) {
            foreach ($this->reflectionModel->getMethod('casts')->getAttributes(TsCasts::class) as $attr) {
                $instance = $attr->newInstance();
                $methodOverrides = array_merge($methodOverrides, $instance->types);
            }
        }

        // Method wins over property wins over class, matching Laravel's own cast resolution
        $merged = array_merge($classOverrides, $propertyOverrides, $methodOverrides);

        foreach ($merged as $column => $value) {
            if (is_array($value)) {
                /** @var array{type: string, import: string} $value */
                $this->tsTypeOverrides[$column] = $value['type'];

                foreach (LaravelTsPublish::extractImportableTypes($value['type']) as $importName) {
                    $this->customImports[$value['import']][] = $importName;
                }
            } else {
                $this->tsTypeOverrides[$column] = $value;
            }
        }

        return $this;
    }

    protected function transformColumns(): self
    {
        /** @var Collection<int, AttributeInfo> $allAttributes */
        $allAttributes = $this->modelInspect['attributes'];

        $attributes = $allAttributes->filter(fn (array $attr) => in_array($attr['name'], $this->dbColumns));

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];

            // #[TsCasts] override takes priority over automatic type resolution
            if (isset($this->tsTypeOverrides[$name])) {
                $this->columns[$name] = $this->tsTypeOverrides[$name];

                continue;
            }

            $cast = $attribute['cast'];

            // When a DB column has an Attribute accessor or old-style mutator,
            // resolve the type from the accessor's get closure / method return type
            // and fall back to the DB column type if no getter exists.
            if ($cast === 'attribute' || $cast === 'accessor') {
                $accessorType = $this->resolveMutatorType($name);
                $typings = $accessorType['type'] !== 'unknown'
                    ? $accessorType
                    : LaravelTsPublish::phpToTypeScriptType($attribute['type']);
            } else {
                $typings = LaravelTsPublish::phpToTypeScriptType($cast ?? $attribute['type']);
            }

            $type = $typings['type'];

            if ($attribute['nullable'] && ! str_contains($type, 'null')) {
                $type .= ' | null';
            }

            $this->columns[$name] = $type;

            foreach ($typings['enumFqcns'] as $i => $fqcn) {
                $this->enumFqcnMap[$fqcn] = $typings['enumTypes'][$i];
            }

            foreach ($typings['classFqcns'] as $i => $fqcn) {
                $this->modelFqcnMap[$fqcn] = $typings['classes'][$i];
            }

            foreach ($typings['customImports'] as $path => $importTypes) {
                $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$importTypes];
            }
        }

        return $this;
    }

    protected function transformMutators(): self
    {
        /** @var Collection<int, AttributeInfo> $allAttributes */
        $allAttributes = $this->modelInspect['attributes'];

        $mutators = $allAttributes->filter(fn (array $attr) => ! in_array($attr['name'], $this->dbColumns));

        foreach ($mutators as $mutator) {
            $name = $mutator['name'];

            // #[TsCasts] override takes priority
            if (isset($this->tsTypeOverrides[$name])) {
                $this->mutators[$name] = $this->tsTypeOverrides[$name];

                continue;
            }

            $resolved = $this->resolveMutatorType($name);
            $this->mutators[$name] = $resolved['type'];

            foreach ($resolved['enumFqcns'] as $i => $fqcn) {
                $this->enumFqcnMap[$fqcn] = $resolved['enumTypes'][$i];
            }

            foreach ($resolved['classFqcns'] as $i => $fqcn) {
                $this->modelFqcnMap[$fqcn] = $resolved['classes'][$i];
            }

            foreach ($resolved['customImports'] as $path => $importTypes) {
                $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$importTypes];
            }
        }

        return $this;
    }

    protected function transformRelations(): self
    {
        $manyRelationTypes = [
            'BelongsToMany',
            'HasMany',
            'HasManyThrough',
            'MorphToMany',
            'MorphMany',
            'MorphedByMany',
        ];

        /** @var Collection<int, RelationInfo> $allRelations */
        $allRelations = $this->modelInspect['relations'];

        /** @var list<string> $includedModels */
        $includedModels = array_values(array_filter(config()->array('ts-publish.included_models', []), 'is_string'));

        /** @var list<string> $excludedModels */
        $excludedModels = array_values(array_filter(config()->array('ts-publish.excluded_models', []), 'is_string'));

        $case = config()->string('ts-publish.relationship_case');

        $relations = $allRelations
            ->when(
                $includedModels,
                fn (Collection $relations, array $included) => $relations->filter(fn (array $relation) => in_array($relation['related'], $included))
            )
            ->when(
                $excludedModels,
                fn (Collection $relations, array $excluded) => $relations->filter(fn (array $relation) => ! in_array($relation['related'], $excluded))
            );

        foreach ($relations as $relation) {
            $relatedBasename = class_basename($relation['related']);

            $relationType = in_array($relation['type'], $manyRelationTypes, true)
                ? $relatedBasename.'[]'
                : $relatedBasename;

            $relationName = LaravelTsPublish::keyCase($relation['name'], $case);
            $this->relations[$relationName] = $relationType;
            $this->modelFqcnMap[$relation['related']] = $relatedBasename;
            $this->modelFqcnRelations[$relation['related']][] = $relation['name'];
        }

        return $this;
    }

    /** @return TypeScriptTypeInfo */
    protected function resolveMutatorType(string $name): array
    {
        $result = LaravelTsPublish::emptyTypeScriptInfo();
        $newStyle = Str::camel($name);
        $oldStyle = 'get'.Str::studly($name).'Attribute';

        // New-style: protected function titleDisplay(): Attribute
        // Must invoke via reflection because the method is protected
        if ($this->reflectionModel->hasMethod($newStyle)) {
            $method = $this->reflectionModel->getMethod($newStyle);
            $method->setAccessible(true);

            $attrInstance = $method->invoke($this->modelInstance);

            if ($attrInstance instanceof Attribute) {
                if ($attrInstance->get !== null) {
                    /** @var \Closure $getter */
                    $getter = $attrInstance->get;

                    return LaravelTsPublish::closureReturnedTypes($getter);
                }

                // write-only mutator (set only, no get) — not readable on the model shape
                return $result;
            }
        }

        // Old-style: public function getTitleDisplayAttribute($value): string
        if ($this->reflectionModel->hasMethod($oldStyle)) {
            return LaravelTsPublish::methodReturnedTypes($this->reflectionModel, $oldStyle);
        }

        return $result;
    }

    /**
     * Detect conflicting import names and generate aliases.
     *
     * Uses a hybrid strategy: relationship-name-based aliases when a FQCN is
     * referenced by exactly one relation, namespace-segment-based otherwise.
     * Enum FQCNs always use namespace-segment-based aliases.
     */
    protected function resolveImportConflicts(): self
    {
        // Build reverse map: typeName => [{fqcn, kind}]
        /** @var array<string, list<array{fqcn: string, kind: 'enum'|'model'}>> $reverseMap */
        $reverseMap = [];

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $reverseMap[$typeName][] = ['fqcn' => $fqcn, 'kind' => 'enum'];
        }

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            if ($fqcn === $this->findable) {
                continue;
            }
            $reverseMap[$typeName][] = ['fqcn' => $fqcn, 'kind' => 'model'];
        }

        foreach ($reverseMap as $typeName => $entries) {
            $needsAlias = count($entries) > 1 || $typeName === $this->modelName;

            if (! $needsAlias) {
                continue;
            }

            foreach ($entries as $entry) {
                $fqcn = $entry['fqcn'];
                $originalName = $entry['kind'] === 'enum'
                    ? $this->enumFqcnMap[$fqcn]
                    : $this->modelFqcnMap[$fqcn];

                if ($entry['kind'] === 'model') {
                    $relations = $this->modelFqcnRelations[$fqcn] ?? [];

                    if (count($relations) === 1) {
                        $alias = Str::studly($relations[0]).$originalName;
                    } else {
                        $alias = $this->computeNamespacePrefix($fqcn).$originalName;
                    }
                } else {
                    $alias = $this->computeNamespacePrefix($fqcn).$originalName;
                }

                $this->importAliases[$fqcn] = $alias;
            }
        }

        if ($this->importAliases !== []) {
            $this->rewriteTypeReferences();
        }

        return $this;
    }

    /**
     * Compute a distinguishing namespace prefix for alias generation.
     *
     * Strips the configured namespace prefix, removes the class name, walks
     * backwards skipping common segments like Models/Enums, and returns the
     * StudlyCase of the first meaningful segment.
     */
    protected function computeNamespacePrefix(string $fqcn): string
    {
        $namespace = Str::beforeLast($fqcn, '\\');

        $prefix = config()->string('ts-publish.namespace_strip_prefix', '');

        if ($prefix !== '' && str_starts_with($namespace, $prefix)) {
            $namespace = substr($namespace, strlen($prefix));
        }

        $segments = array_filter(explode('\\', $namespace));
        $skip = ['Models', 'Enums', 'App'];

        // Walk backwards to find the first meaningful segment
        foreach (array_reverse($segments) as $segment) {
            if (! in_array($segment, $skip, true)) {
                return Str::studly($segment);
            }
        }

        // Fallback: use the first available segment
        $first = reset($segments);

        return $first !== false ? Str::studly($first) : '';
    }

    /**
     * Rewrite type references in columns, mutators, and relations to use aliases.
     *
     * Relations are rewritten using FQCN-to-relation tracking for precision.
     * Columns and mutators use regex replacement with word boundaries.
     */
    protected function rewriteTypeReferences(): void
    {
        // Build a relation-name → alias lookup from FQCN relationships
        // This allows precise replacement even when multiple FQCNs share the same base name
        /** @var array<string, string> $relationNameToAlias */
        $relationNameToAlias = [];

        foreach ($this->importAliases as $fqcn => $alias) {
            $originalName = $this->modelFqcnMap[$fqcn] ?? null;

            if ($originalName === null || $originalName === $alias) {
                continue;
            }

            foreach ($this->modelFqcnRelations[$fqcn] ?? [] as $relationMethod) {
                $relationKey = LaravelTsPublish::keyCase(
                    $relationMethod,
                    config()->string('ts-publish.relationship_case'),
                );

                $relationNameToAlias[$relationKey] = $alias;
            }
        }

        // Rewrite relation types using precise relation-name mapping
        foreach ($relationNameToAlias as $relationKey => $alias) {
            if (! isset($this->relations[$relationKey])) {
                continue;
            }

            $currentType = $this->relations[$relationKey];
            $isArray = str_ends_with($currentType, '[]');
            $this->relations[$relationKey] = $alias.($isArray ? '[]' : '');
        }

        // Rewrite column and mutator types using regex (safe for enums and single-FQCN models)
        foreach ($this->importAliases as $fqcn => $alias) {
            $originalName = $this->enumFqcnMap[$fqcn] ?? $this->modelFqcnMap[$fqcn] ?? null;

            if ($originalName === null || $originalName === $alias) {
                continue;
            }

            $pattern = '/(?<![A-Za-z0-9_$])'.preg_quote($originalName, '/').'(?![A-Za-z0-9_$])/';

            foreach ($this->columns as $key => $type) {
                $this->columns[$key] = preg_replace($pattern, $alias, $type) ?? $type;
            }

            foreach ($this->mutators as $key => $type) {
                $this->mutators[$key] = preg_replace($pattern, $alias, $type) ?? $type;
            }
        }
    }

    /**
     * Build the resolved imports map from accumulated FQCNs and custom imports.
     *
     * @return ResolvedImportMap
     */
    protected function buildResolvedImports(): array
    {
        $resolvedImports = [];
        $isModular = config()->boolean('ts-publish.modular_publishing');

        if ($isModular) {
            foreach ($this->enumFqcnMap as $fqcn => $typeName) {
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $resolvedImports[$importPath][] = $this->formatImportName($fqcn, $typeName);
            }

            foreach ($this->modelFqcnMap as $fqcn => $typeName) {
                if ($fqcn === $this->findable) {
                    continue;
                }
                $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                $resolvedImports[$importPath][] = $this->formatImportName($fqcn, $typeName);
            }
        } else {
            $enumImports = [];
            foreach ($this->enumFqcnMap as $fqcn => $typeName) {
                $enumImports[] = $this->formatImportName($fqcn, $typeName);
            }
            $enumImports = array_values(array_unique($enumImports));

            if ($enumImports) {
                sort($enumImports);
                $resolvedImports['../enums'] = $enumImports;
            }

            $modelImports = [];
            foreach ($this->modelFqcnMap as $fqcn => $typeName) {
                if ($fqcn === $this->findable) {
                    continue;
                }
                $modelImports[] = $this->formatImportName($fqcn, $typeName);
            }
            $modelImports = array_values(array_unique($modelImports));

            if ($modelImports) {
                sort($modelImports);
                $resolvedImports['./'] = $modelImports;
            }
        }

        // Merge custom imports
        foreach ($this->customImports as $path => $types) {
            $existing = $resolvedImports[$path] ?? [];
            $resolvedImports[$path] = array_values(array_unique([...$existing, ...$types]));
        }

        // Deduplicate per path
        foreach ($resolvedImports as $path => $types) {
            $resolvedImports[$path] = array_values(array_unique($types));
        }

        return LaravelTsPublish::sortImportPaths($resolvedImports);
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
}
