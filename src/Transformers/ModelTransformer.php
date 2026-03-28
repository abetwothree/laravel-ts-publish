<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesAccessorType;
use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;
use AbeTwoThree\LaravelTsPublish\Dtos\TsModelDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\RelationNullable;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ParsesTsExtends;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\TracksEnumImports;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
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
 * @phpstan-import-type AttributeInfo from ModelInfo
 * @phpstan-import-type RelationInfo from ModelInfo
 *
 * @phpstan-type DbColumns = list<string>
 * @phpstan-type TsTypeOverrides = array<string, string>
 *
 * @extends CoreTransformer<Model>
 */
class ModelTransformer extends CoreTransformer
{
    use BuildsImportMaps;
    use ParsesTsCasts;
    use ParsesTsExtends;
    use ResolvesAccessorType;
    use ResolvesImportConflicts;
    use TracksEnumImports;

    public protected(set) string $modelName;

    public protected(set) string $filePath;

    public protected(set) string $namespacePath;

    public protected(set) string $description = '';

    public protected(set) Model $modelInstance;

    /** @var ReflectionClass<Model> */
    public protected(set) ReflectionClass $reflectionModel;

    /** @var DbColumns */
    public protected(set) array $dbColumns = [];

    /** @var ModelInfo<Model> */
    public protected(set) ModelInfo $modelInspect;

    /** @var ColumnsList */
    public protected(set) array $columns = [];

    /** @var MutatorsList */
    public protected(set) array $mutators = [];

    /** @var RelationsList */
    public protected(set) array $relations = [];

    /** @var TsTypeOverrides */
    public protected(set) array $tsTypeOverrides = [];

    protected RelationNullable $relationNullable;

    /** @var array<string, string> FQCN => TypeScript short name */
    protected array $modelFqcnMap = [];

    /** @var array<string, list<string>> FQCN => list of relation method names that reference it */
    protected array $modelFqcnRelations = [];

    /** @var array<string, list<string>> column_name => list of FQCNs (enum or model) referenced by that column */
    protected array $columnFqcns = [];

    /** @var array<string, list<string>> mutator_name => list of FQCNs (enum or model) referenced by that mutator */
    protected array $mutatorFqcns = [];

    /** @var array<string, list<string>> */
    public protected(set) array $customImports = [];

    /** @var array<string, array{fqcn: string, nullable: bool}> column_name => enum property info */
    protected array $enumColumnProperties = [];

    /** @var array<string, array{fqcn: string, nullable: bool}> mutator_name => enum property info */
    protected array $enumMutatorProperties = [];

    /** @var list<string> TypeScript extends clauses */
    public protected(set) array $tsExtends = [];

    #[Override]
    public function transform(): self
    {
        $this->initInstance()
            ->parseTsExtends()
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
            tsExtends: $this->tsExtends,
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
        /** @var Collection<int, AttributeInfo> $attributes */
        $attributes = $this->modelInspect->attributes;
        $this->relationNullable = new RelationNullable($this->modelInstance, $attributes);
        $this->reflectionModel = new ReflectionClass($this->findable);
        $this->modelName = $this->reflectionModel->getShortName();
        $this->filePath = $this->resolveRelativePath((string) $this->reflectionModel->getFileName());
        $this->namespacePath = LaravelTsPublish::namespaceToPath($this->findable);
        $this->description = LaravelTsPublish::parseDocBlockDescription($this->reflectionModel->getDocComment());

        return $this;
    }

    protected function parseTsExtends(): self
    {
        $result = $this->parseTsExtendsFromReflection($this->reflectionModel, 'models');

        $this->tsExtends = $result['extends'];

        foreach ($result['imports'] as $importPath => $typeNames) {
            $this->customImports[$importPath] = [...($this->customImports[$importPath] ?? []), ...$typeNames];
        }

        return $this;
    }

    protected function parseTsTypeOverrides(): self
    {
        $result = $this->parseTsCastsFromReflection($this->reflectionModel);

        $this->tsTypeOverrides = $result['overrides'];

        foreach ($result['importPaths'] as $column => $importPath) {
            foreach (LaravelTsPublish::extractImportableTypes($result['overrides'][$column]) as $importName) {
                $this->customImports[$importPath][] = $importName;
            }
        }

        return $this;
    }

    protected function transformColumns(): self
    {
        /** @var Collection<int, AttributeInfo> $allAttributes */
        $allAttributes = $this->modelInspect->attributes;

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
                    : LaravelTsPublish::phpToTypeScriptType($attribute['type'] ?? '');
            } else {
                $typings = LaravelTsPublish::phpToTypeScriptType($cast ?? $attribute['type'] ?? '');
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
        $allAttributes = $this->modelInspect->attributes;

        $mutators = $allAttributes->filter(fn (array $attr) => ! in_array($attr['name'], $this->dbColumns));

        foreach ($mutators as $mutator) {
            $name = $mutator['name'];

            if ($this->isMutatorExcluded($name)) {
                continue;
            }

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
        $allRelations = $this->modelInspect->relations;

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
            if ($this->reflectionModel->hasMethod($relation['name'])
                && $this->reflectionModel->getMethod($relation['name'])->getAttributes(TsExclude::class) !== []
            ) {
                continue;
            }

            $relatedBasename = class_basename($relation['related']);
            $containsMany = str_contains(strtolower($relation['type']), 'many');

            $relationType = $containsMany
                ? $relatedBasename.'[]'
                : $relatedBasename;

            if ($nullableRelations && $this->relationNullable->isNullable($relation)) {
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

    protected function isMutatorExcluded(string $name): bool
    {
        $newStyle = Str::camel($name);
        $oldStyle = 'get'.Str::studly($name).'Attribute';

        if ($this->reflectionModel->hasMethod($newStyle)
            && $this->reflectionModel->getMethod($newStyle)->getAttributes(TsExclude::class) !== []
        ) {
            return true;
        }

        if ($this->reflectionModel->hasMethod($oldStyle)
            && $this->reflectionModel->getMethod($oldStyle)->getAttributes(TsExclude::class) !== []
        ) {
            return true;
        }

        return false;
    }

    /** @return TypeScriptTypeInfo */
    protected function resolveMutatorType(string $name): array
    {
        return $this->resolveAccessorType($name, $this->modelInstance, $this->reflectionModel);
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

        // Filter out self-references from model FQCN map
        $modelFqcnMap = array_filter(
            $this->modelFqcnMap,
            fn (string $typeName, string $fqcn) => $fqcn !== $this->findable,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($isModular) {
            $typeImports = [
                ...$this->collectModularTypeImports($this->enumFqcnMap),
                ...$this->collectModularTypeImports($modelFqcnMap),
            ];

            if ($hasEnums) {
                $valueImports = $this->collectModularValueImports($this->enumPropertyFqcns());
            }
        } else {
            ['typeImports' => $typeImports, 'valueImports' => $valueImports] = $this->buildFlatEnumImports(
                $this->enumFqcnMap,
                $this->enumPropertyFqcns(),
                $hasEnums,
            );

            $this->addSortedImports($typeImports, './', $this->collectFlatTypeImports($modelFqcnMap));
        }

        $typeImports = $this->mergeCustomImports($typeImports, $this->customImports);

        return [
            'typeImports' => $this->deduplicateAndSortImports($typeImports),
            'valueImports' => $this->deduplicateAndSortImports($valueImports),
        ];
    }

    /**
     * Build a map of per-file import aliases → namespace-qualified global names.
     *
     * Used by GlobalsWriter to resolve aliases (e.g. `CrmUser`, `WorkbenchStatusType`) back to
     * their correct globally-qualified names before the normal `qualifyGlobalType()` pass.
     *
     * @return array<string, string> alias => 'namespace.OriginalName'
     */
    public function globalAliasMap(): array
    {
        $isModular = config()->boolean('ts-publish.modular_publishing');
        $modelsNs = config()->string('ts-publish.models_namespace');
        $enumsNs = config()->string('ts-publish.enums_namespace');
        $map = [];

        foreach ($this->importAliases as $fqcn => $alias) {
            if (isset($this->enumFqcnMap[$fqcn])) {
                $ns = $isModular
                    ? str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn))
                    : $enumsNs;
                $map[$alias] = $ns.'.'.$this->enumFqcnMap[$fqcn];
            } elseif (isset($this->modelFqcnMap[$fqcn])) {
                $ns = $isModular
                    ? str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn))
                    : $modelsNs;
                $map[$alias] = $ns.'.'.$this->modelFqcnMap[$fqcn];
            }
        }

        return $map;
    }

    #[Override]
    protected function enumProperties(): array
    {
        return array_merge($this->enumColumnProperties, $this->enumMutatorProperties);
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
