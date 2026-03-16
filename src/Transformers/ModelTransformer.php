<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\TsModelDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type ColumnsList from TsModelDto
 * @phpstan-import-type TypesImportMap from TsModelDto
 * @phpstan-import-type ValuesImportMap from TsModelDto
 * @phpstan-import-type MutatorsList from TsModelDto
 * @phpstan-import-type RelationsList from TsModelDto
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
 * @phpstan-type TsTypeOverrides = array<string, string>
 *
 * @extends CoreTransformer<Model>
 */
class ModelTransformer extends CoreTransformer
{
    public protected(set) string $modelName;

    public protected(set) string $filePath;

    public protected(set) string $namespacePath;

    public protected(set) string $description = '';

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

    /** @var array<string, list<string>> column_name => list of FQCNs (enum or model) referenced by that column */
    protected array $columnFqcns = [];

    /** @var array<string, list<string>> mutator_name => list of FQCNs (enum or model) referenced by that mutator */
    protected array $mutatorFqcns = [];

    /** @var array<string, list<string>> */
    protected array $customImports = [];

    /** @var array<string, string> FQCN => TypeScript const name (e.g. 'Status') */
    protected array $enumConstMap = [];

    /** @var array<string, array{fqcn: string, nullable: bool}> column_name => enum property info */
    protected array $enumColumnProperties = [];

    /** @var array<string, array{fqcn: string, nullable: bool}> mutator_name => enum property info */
    protected array $enumMutatorProperties = [];

    /** @var array<string, string> FQCN => aliased TypeScript const name (only for conflicting imports) */
    protected array $constImportAliases = [];

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
     * Get the transformed data as a structured DTO.
     */
    #[Override]
    public function data(): TsModelDto
    {
        $hasEnums = $this->shouldGenerateHasEnums();
        $imports = $this->buildResolvedImports();

        return new TsModelDto(
            modelName: $this->modelName,
            description: $this->description,
            filePath: $this->filePath,
            filename: $this->filename(),
            columns: $this->columns,
            mutators: $this->mutators,
            relations: $this->relations,
            typeImports: $imports['typeImports'],
            valueImports: $imports['valueImports'],
            enumColumns: $hasEnums ? $this->buildEnumColumns() : [],
            enumMutators: $hasEnums ? $this->buildEnumMutators() : [],
        );
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
        $this->description = LaravelTsPublish::parseDocBlockDescription($this->reflectionModel->getDocComment());

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
                $this->columns[$name] = ['type' => $this->tsTypeOverrides[$name], 'description' => ''];

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

            $this->columns[$name] = ['type' => $type, 'description' => $this->resolveAccessorDescription($name)];

            foreach ($typings['enumFqcns'] as $i => $fqcn) {
                $this->enumFqcnMap[$fqcn] = $typings['enumTypes'][$i];
                $this->enumConstMap[$fqcn] = $typings['enums'][$i];
                $this->columnFqcns[$name][] = $fqcn;
            }

            if ($typings['enumFqcns'] !== []) {
                $this->enumColumnProperties[$name] = [
                    'fqcn' => $typings['enumFqcns'][0],
                    'nullable' => $attribute['nullable'],
                ];
            }

            foreach ($typings['classFqcns'] as $i => $fqcn) {
                $this->modelFqcnMap[$fqcn] = $typings['classes'][$i];
                $this->columnFqcns[$name][] = $fqcn;
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
                $this->mutators[$name] = ['type' => $this->tsTypeOverrides[$name], 'description' => ''];

                continue;
            }

            $resolved = $this->resolveMutatorType($name);
            $this->mutators[$name] = ['type' => $resolved['type'], 'description' => $this->resolveAccessorDescription($name)];

            foreach ($resolved['enumFqcns'] as $i => $fqcn) {
                $this->enumFqcnMap[$fqcn] = $resolved['enumTypes'][$i];
                $this->enumConstMap[$fqcn] = $resolved['enums'][$i];
                $this->mutatorFqcns[$name][] = $fqcn;
            }

            if ($resolved['enumFqcns'] !== []) {
                $this->enumMutatorProperties[$name] = [
                    'fqcn' => $resolved['enumFqcns'][0],
                    'nullable' => str_contains($resolved['type'], 'null'),
                ];
            }

            foreach ($resolved['classFqcns'] as $i => $fqcn) {
                $this->modelFqcnMap[$fqcn] = $resolved['classes'][$i];
                $this->mutatorFqcns[$name][] = $fqcn;
            }

            foreach ($resolved['customImports'] as $path => $importTypes) {
                $this->customImports[$path] = [...($this->customImports[$path] ?? []), ...$importTypes];
            }
        }

        return $this;
    }

    protected function transformRelations(): self
    {
        /** @var Collection<int, RelationInfo> $allRelations */
        $allRelations = $this->modelInspect['relations'];

        /** @var list<string> $includedModels */
        $includedModels = array_values(array_filter(config()->array('ts-publish.included_models', []), 'is_string'));

        /** @var list<string> $excludedModels */
        $excludedModels = array_values(array_filter(config()->array('ts-publish.excluded_models', []), 'is_string'));

        $case = config()->string('ts-publish.relationship_case');
        $nullableRelations = config()->boolean('ts-publish.nullable_relations');

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
            $containsMany = str_contains(strtolower($relation['type']), 'many');

            $relationType = $containsMany
                ? $relatedBasename.'[]'
                : $relatedBasename;

            if ($nullableRelations && $this->isRelationNullable($relation)) {
                $relationType .= ' | null';
            }

            $relationName = LaravelTsPublish::keyCase($relation['name'], $case);

            $description = '';
            if ($this->reflectionModel->hasMethod($relation['name'])) {
                $description = LaravelTsPublish::parseDocBlockDescription(
                    $this->reflectionModel->getMethod($relation['name'])->getDocComment()
                );
            }

            $this->relations[$relationName] = ['type' => $relationType, 'description' => $description];
            $this->modelFqcnMap[$relation['related']] = $relatedBasename;
            $this->modelFqcnRelations[$relation['related']][] = $relation['name'];
        }

        return $this;
    }

    /**
     * Determine whether a singular relation should be typed as nullable.
     *
     * @param  RelationInfo  $relation
     */
    protected function isRelationNullable(array $relation): bool
    {
        $strategy = LaravelTsPublish::relationStrategy($relation['type']);

        return match ($strategy) {
            'never' => false,
            'nullable' => true,
            'fk' => $this->isForeignKeyNullable($relation),
            'morph' => $this->isMorphNullable($relation),
            default => true,
        };
    }

    /**
     * Check if the BelongsTo foreign key column is nullable in the DB schema.
     *
     * @param  RelationInfo  $relation
     */
    protected function isForeignKeyNullable(array $relation): bool
    {
        $relationInstance = $this->modelInstance->{$relation['name']}();

        if (! $relationInstance instanceof BelongsTo) {
            return true;
        }

        $fkName = $relationInstance->getForeignKeyName();

        return $this->isAttributeNullable($fkName);
    }

    /**
     * Check if morph type or morph id columns are nullable in the DB schema.
     *
     * @param  RelationInfo  $relation
     */
    protected function isMorphNullable(array $relation): bool
    {
        $relationInstance = $this->modelInstance->{$relation['name']}();

        if (! $relationInstance instanceof MorphTo) {
            return true;
        }

        $fkName = $relationInstance->getForeignKeyName();
        $morphType = $relationInstance->getMorphType();

        return $this->isAttributeNullable($fkName) || $this->isAttributeNullable($morphType);
    }

    protected function isAttributeNullable(string $columnName): bool
    {
        /** @var Collection<int, AttributeInfo> $attributes */
        $attributes = $this->modelInspect['attributes'];

        $attribute = $attributes->first(fn (array $attr) => $attr['name'] === $columnName);

        return $attribute !== null ? $attribute['nullable'] : true;
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

    protected function resolveAccessorDescription(string $name): string
    {
        $newStyle = Str::camel($name);
        $oldStyle = 'get'.Str::studly($name).'Attribute';

        if ($this->reflectionModel->hasMethod($newStyle)) {
            $desc = LaravelTsPublish::parseDocBlockDescription(
                $this->reflectionModel->getMethod($newStyle)->getDocComment()
            );

            if ($desc !== '') {
                return $desc;
            }
        }

        if ($this->reflectionModel->hasMethod($oldStyle)) {
            return LaravelTsPublish::parseDocBlockDescription(
                $this->reflectionModel->getMethod($oldStyle)->getDocComment()
            );
        }

        return '';
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
                    $prefix = $this->computeNamespacePrefix($fqcn);
                    $alias = $prefix.$originalName;

                    if (isset($this->enumConstMap[$fqcn])) {
                        $this->constImportAliases[$fqcn] = $prefix.$this->enumConstMap[$fqcn];
                    }
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

            $currentType = $this->relations[$relationKey]['type'];
            $isArray = str_ends_with($currentType, '[]');
            $isNullable = str_contains($currentType, '| null');
            $this->relations[$relationKey]['type'] = $alias.($isArray ? '[]' : '').($isNullable ? ' | null' : '');
        }

        // Rewrite column and mutator types using precise FQCN→column tracking
        foreach ($this->importAliases as $fqcn => $alias) {
            $originalName = $this->enumFqcnMap[$fqcn] ?? $this->modelFqcnMap[$fqcn] ?? null;

            if ($originalName === null || $originalName === $alias) {
                continue;
            }

            $pattern = '/(?<![A-Za-z0-9_$])'.preg_quote($originalName, '/').'(?![A-Za-z0-9_$])/';

            foreach ($this->columns as $key => $entry) {
                if (! in_array($fqcn, $this->columnFqcns[$key] ?? [])) {
                    continue;
                }
                $this->columns[$key]['type'] = preg_replace($pattern, $alias, $entry['type']) ?? $entry['type'];
            }

            foreach ($this->mutators as $key => $entry) {
                if (! in_array($fqcn, $this->mutatorFqcns[$key] ?? [])) {
                    continue;
                }
                $this->mutators[$key]['type'] = preg_replace($pattern, $alias, $entry['type']) ?? $entry['type'];
            }
        }
    }

    /**
     * Build the type and value import maps from accumulated FQCNs and custom imports.
     *
     * @return array{typeImports: TypesImportMap, valueImports: ValuesImportMap}
     */
    protected function buildResolvedImports(): array
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
                foreach ($this->enumPropertyFqcns() as $fqcn) {
                    $targetPath = LaravelTsPublish::namespaceToPath($fqcn);
                    $importPath = LaravelTsPublish::relativeImportPath($this->namespacePath, $targetPath);
                    $valueImports[$importPath][] = $this->formatConstImportName($fqcn);
                }
            }

            foreach ($this->modelFqcnMap as $fqcn => $typeName) {
                if ($fqcn === $this->findable) {
                    continue;
                }
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

            if ($enumTypeImports) {
                sort($enumTypeImports);
                $typeImports['../enums'] = $enumTypeImports;
            }

            if ($hasEnums) {
                $enumValueImports = [];
                foreach ($this->enumPropertyFqcns() as $fqcn) {
                    $enumValueImports[] = $this->formatConstImportName($fqcn);
                }
                $enumValueImports = array_values(array_unique($enumValueImports));

                if ($enumValueImports) {
                    sort($enumValueImports);
                    $valueImports['../enums'] = $enumValueImports;
                }
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
                $typeImports['./'] = $modelImports;
            }
        }

        // Merge custom imports into type imports
        foreach ($this->customImports as $path => $types) {
            $existing = $typeImports[$path] ?? [];
            $typeImports[$path] = array_values(array_unique([...$existing, ...$types]));
        }

        // Deduplicate per path
        foreach ($typeImports as $path => $types) {
            $uniqueTypes = array_values(array_unique($types));
            sort($uniqueTypes);
            $typeImports[$path] = $uniqueTypes;
        }

        foreach ($valueImports as $path => $types) {
            $uniqueTypes = array_values(array_unique($types));
            sort($uniqueTypes);
            $valueImports[$path] = $uniqueTypes;
        }

        return [
            'typeImports' => LaravelTsPublish::sortImportPaths($typeImports),
            'valueImports' => LaravelTsPublish::sortImportPaths($valueImports),
        ];
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

    protected function shouldGenerateHasEnums(): bool
    {
        return config()->boolean('ts-publish.enums_use_tolki_package')
            && ($this->enumColumnProperties !== [] || $this->enumMutatorProperties !== []);
    }

    /** @return list<string> */
    protected function enumPropertyFqcns(): array
    {
        return array_values(array_unique([
            ...array_column($this->enumColumnProperties, 'fqcn'),
            ...array_column($this->enumMutatorProperties, 'fqcn'),
        ]));
    }

    protected function formatConstImportName(string $fqcn): string
    {
        $constName = $this->enumConstMap[$fqcn];
        $alias = $this->constImportAliases[$fqcn] ?? null;

        if ($alias !== null && $alias !== $constName) {
            return $constName.' as '.$alias;
        }

        return $constName;
    }

    /**
     * @return array<string, array{constName: string, nullable: bool}>
     */
    protected function buildEnumColumns(): array
    {
        $result = [];

        foreach ($this->enumColumnProperties as $name => $info) {
            $result[$name] = [
                'constName' => $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']],
                'nullable' => $info['nullable'],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array{constName: string, nullable: bool}>
     */
    protected function buildEnumMutators(): array
    {
        $result = [];

        foreach ($this->enumMutatorProperties as $name => $info) {
            $result[$name] = [
                'constName' => $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']],
                'nullable' => $info['nullable'],
            ];
        }

        return $result;
    }
}
