<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesAccessorType;
use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;
use AbeTwoThree\LaravelTsPublish\Dtos\TsModelDto;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\RelationNullable;
use AbeTwoThree\LaravelTsPublish\Support\ImportNameRegistry;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\BuildsImportMaps;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ParsesTsExtends;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\ResolvesImportConflicts;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\SnapshotsTransformerState;
use AbeTwoThree\LaravelTsPublish\Transformers\Concerns\TracksEnumImports;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type ColumnsList from TsModelDto
 * @phpstan-import-type TypesImportMap from TsModelDto
 * @phpstan-import-type ValuesImportMap from TsModelDto
 * @phpstan-import-type MutatorsList from TsModelDto
 * @phpstan-import-type AppendsList from TsModelDto
 * @phpstan-import-type RelationsList from TsModelDto
 * @phpstan-import-type EnumPropertyInfo from TsModelDto
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
    use SnapshotsTransformerState;
    use TracksEnumImports;

    public protected(set) string $modelName;

    public protected(set) string $filePath;

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

    /** @var AppendsList */
    public protected(set) array $appends = [];

    /** @var RelationsList */
    public protected(set) array $relations = [];

    /** @var TsTypeOverrides */
    public protected(set) array $tsTypeOverrides = [];

    /** @var array<string, bool> */
    protected array $optionalOverrides = [];

    protected RelationNullable $relationNullable;

    /** @var array<string, string> FQCN => TypeScript short name */
    protected array $modelFqcnMap = [];

    /** @var array<string, list<string>> FQCN => list of relation method names that reference it */
    protected array $modelFqcnRelations = [];

    /** @var array<string, list<string>> column_name => list of FQCNs (enum or model) referenced by that column */
    protected array $columnFqcns = [];

    /** @var array<string, list<string>> mutator_name => list of FQCNs (enum or model) referenced by that mutator */
    protected array $mutatorFqcns = [];

    /** @var array<string, list<string>> append_name => list of FQCNs (enum or model) referenced by that append */
    protected array $appendsFqcns = [];

    /** @var array<string, list<string>> relation_name => target FQCNs, one per occurrence, in type-string order */
    protected array $relationFqcns = [];

    /** @var array<string, list<string>> */
    public protected(set) array $customImports = [];

    /** @var array<string, array{fqcn: string, nullable: bool, isCollection: bool}> column_name => enum property info */
    protected array $enumColumnProperties = [];

    /** @var array<string, array{fqcn: string, nullable: bool, isCollection: bool}> mutator_name => enum property info */
    protected array $enumMutatorProperties = [];

    /** @var array<string, array{fqcn: string, nullable: bool, isCollection: bool}> append_name => enum property info */
    protected array $enumAppendsProperties = [];

    /** @var list<string> Attribute names from model's array */
    protected array $appendedAttributes = [];

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
            fqcn: $this->fqcn(),
            filePath: $this->filePath,
            filename: $this->filename(),
            columns: $this->columns,
            mutators: $this->mutators,
            appends: $this->appends,
            relations: $this->relations,
            typeImports: $imports['typeImports'],
            valueImports: $imports['valueImports'],
            enumColumns: $hasEnums ? $this->buildEnumColumns() : [],
            enumMutators: $hasEnums ? $this->buildEnumMutators() : [],
            enumAppends: $hasEnums ? $this->buildEnumAppends() : [],
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
        $this->appendedAttributes = $this->modelInstance->getAppends();
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
        $this->optionalOverrides = $result['optionalOverrides'];

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

        $resolver = resolve(ModelAttributeResolver::class);
        $excludeHidden = $resolver->excludeHiddenAttributes();

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];

            // Opt-in via ts-publish.models.exclude_hidden: matches Laravel's own serialization, where
            // a $hidden column never reaches toArray()/toJson(). Off by default for backwards compat.
            if ($excludeHidden && $attribute['hidden']) {
                continue;
            }

            if (isset($this->tsTypeOverrides[$name])) {
                $this->columns[$name] = ['type' => $this->tsTypeOverrides[$name], 'description' => '', 'optional' => $this->optionalOverrides[$name] ?? false];

                continue;
            }

            $cast = $attribute['cast'];

            // Resolves through the accessor → cast → DB type waterfall.
            $typings = $resolver->resolveAttribute($this->findable, $name);

            // Fall back to the raw cast so enum and class metadata still propagates.
            if ($typings['type'] === 'unknown') {
                $typings = match ($cast) {
                    'attribute', 'accessor' => LaravelTsPublish::toTsType($attribute['type'] ?? ''),
                    default => LaravelTsPublish::toTsType($cast ?? $attribute['type'] ?? ''),
                };
            }

            $type = $typings['type'];

            if ($attribute['nullable'] && ! str_contains($type, 'null')) {
                $type .= ' | null';
            }

            $this->columns[$name] = ['type' => $type, 'description' => $this->resolveAccessorDescription($name), 'optional' => $this->optionalOverrides[$name] ?? false];

            foreach ($typings['enumFqcns'] as $i => $fqcn) {
                $this->enumFqcnMap[$fqcn] = $typings['enumTypes'][$i];
                $this->enumConstMap[$fqcn] = $typings['enums'][$i];
                $this->columnFqcns[$name][] = $fqcn;
            }

            if ($typings['enumFqcns'] !== []) {
                // $type itself may already carry '| null' here: resolveAttribute() appends it
                // internally before this point, so the suffix check must strip it first.
                $this->enumColumnProperties[$name] = [
                    'fqcn' => $typings['enumFqcns'][0],
                    'nullable' => $attribute['nullable'],
                    'isCollection' => str_ends_with(rtrim(str_replace('| null', '', $type)), '[]'),
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

            $isAppended = in_array($name, $this->appendedAttributes, true);

            if (isset($this->tsTypeOverrides[$name])) {
                if ($isAppended) {
                    $this->appends[$name] = ['type' => $this->tsTypeOverrides[$name], 'description' => '', 'optional' => $this->optionalOverrides[$name] ?? false];
                } else {
                    $this->mutators[$name] = ['type' => $this->tsTypeOverrides[$name], 'description' => '', 'optional' => $this->optionalOverrides[$name] ?? false];
                }

                continue;
            }

            $resolved = $this->resolveMutatorType($name);

            // No getter, no docblock generic, no backing column: nothing to publish for this name.
            if ($resolved['omit'] ?? false) {
                continue;
            }

            if ($isAppended) {
                $this->appends[$name] = ['type' => $resolved['type'], 'description' => $this->resolveAccessorDescription($name), 'optional' => $this->optionalOverrides[$name] ?? false];
            } else {
                $this->mutators[$name] = ['type' => $resolved['type'], 'description' => $this->resolveAccessorDescription($name), 'optional' => $this->optionalOverrides[$name] ?? false];
            }

            foreach ($resolved['enumFqcns'] as $i => $fqcn) {
                $this->enumFqcnMap[$fqcn] = $resolved['enumTypes'][$i];
                $this->enumConstMap[$fqcn] = $resolved['enums'][$i];

                if ($isAppended) {
                    $this->appendsFqcns[$name][] = $fqcn;
                } else {
                    $this->mutatorFqcns[$name][] = $fqcn;
                }
            }

            if ($resolved['enumFqcns'] !== []) {
                $enumInfo = [
                    'fqcn' => $resolved['enumFqcns'][0],
                    'nullable' => str_contains($resolved['type'], 'null'),
                    'isCollection' => str_ends_with(rtrim(str_replace('| null', '', $resolved['type'])), '[]'),
                ];

                if ($isAppended) {
                    $this->enumAppendsProperties[$name] = $enumInfo;
                } else {
                    $this->enumMutatorProperties[$name] = $enumInfo;
                }
            }

            foreach ($resolved['classFqcns'] as $i => $fqcn) {
                $this->modelFqcnMap[$fqcn] = $resolved['classes'][$i];

                if ($isAppended) {
                    $this->appendsFqcns[$name][] = $fqcn;
                } else {
                    $this->mutatorFqcns[$name][] = $fqcn;
                }
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
        $includedModels = array_values(array_filter(Config::array('ts-publish.models.included', []), 'is_string'));

        /** @var list<string> $excludedModels */
        $excludedModels = array_values(array_filter(Config::array('ts-publish.models.excluded', []), 'is_string'));

        $case = Config::string('ts-publish.models.relationship_case');
        $nullableRelations = Config::boolean('ts-publish.models.nullable_relations');

        $isMorphToRelation = static function (array $relation): bool {
            /** @var string $type */
            $type = $relation['type'];

            return $type === 'MorphTo'
                || (str_ends_with($type, 'MorphTo') && ! str_ends_with($type, 'MorphToMany'));
        };

        $relations = $allRelations
            ->when(
                $includedModels,
                fn (Collection $relations, array $included) => $relations->filter(
                    fn (array $relation) => $isMorphToRelation($relation) || in_array($relation['related'], $included)
                )
            )
            ->when(
                $excludedModels,
                fn (Collection $relations, array $excluded) => $relations->filter(
                    fn (array $relation) => $isMorphToRelation($relation) || ! in_array($relation['related'], $excluded)
                )
            );

        foreach ($relations as $relation) {
            if ($this->reflectionModel->hasMethod($relation['name'])
                && $this->reflectionModel->getMethod($relation['name'])->getAttributes(TsExclude::class) !== []
            ) {
                continue;
            }

            $isMorphTo = $isMorphToRelation($relation);
            $relatedBasename = class_basename($relation['related']);
            $containsMany = str_contains(strtolower($relation['type']), 'many');

            // Default for a non-morph relation; the morph branch below overrides it either way.
            $relationTargetFqcns = [$relation['related']];

            if ($isMorphTo) {
                /** @var ModelAttributeResolver $resolver */
                $resolver = resolve(ModelAttributeResolver::class);
                $morphTargets = $resolver->resolveMorphToTargets($this->findable, $relation['name']);

                if ($includedModels !== []) {
                    $morphTargets = array_values(array_filter(
                        $morphTargets,
                        fn (string $fqcn) => in_array($fqcn, $includedModels, true),
                    ));
                }

                if ($excludedModels !== []) {
                    $morphTargets = array_values(array_filter(
                        $morphTargets,
                        fn (string $fqcn) => ! in_array($fqcn, $excludedModels, true),
                    ));
                }

                if ($morphTargets !== []) {
                    $relationType = implode(' | ', array_map(class_basename(...), $morphTargets));
                    $relationTargetFqcns = $morphTargets;

                    foreach ($morphTargets as $targetFqcn) {
                        $targetBasename = class_basename($targetFqcn);
                        $this->modelFqcnMap[$targetFqcn] = $targetBasename;
                        $this->modelFqcnRelations[$targetFqcn][] = $relation['name'];
                    }
                } else {
                    $relationType = 'unknown';
                    $relationTargetFqcns = [];
                }
            } elseif ($containsMany) {
                $relationType = $relatedBasename.'[]';
            } else {
                $relationType = $relatedBasename;
            }

            // 'unknown' already admits null, so appending the suffix would only add noise.
            if ($relationType !== 'unknown' && $nullableRelations && $this->relationNullable->isNullable($relation)) {
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
            $this->relationFqcns[$relationName] = $relationTargetFqcns;

            if (! $isMorphTo) {
                $this->modelFqcnMap[$relation['related']] = $relatedBasename;
                $this->modelFqcnRelations[$relation['related']][] = $relation['name'];
            }
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
        $accessorInfo = $this->resolveAccessorType($name, $this->modelInstance, $this->reflectionModel);

        // Mutators resolve accessors directly rather than through resolveAttribute()'s own cast/DB
        // waterfall, so a vague native return type (e.g. old-style `: array`) needs its own refinement pass.
        return resolve(ModelAttributeResolver::class)->refineWithPropertyDocblock($this->reflectionModel, $name, $accessorInfo);
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
     * A model FQCN used by exactly one relation is aliased from that relation's name; everything
     * else, enums included, is aliased from its namespace segment.
     */
    protected function resolveImportConflicts(): self
    {
        $registry = new ImportNameRegistry;
        $registry->reserve($this->modelName);

        // A sibling registry resolves const names independently rather than string-slicing the type
        // alias, which breaks on a numeric tiebreak suffix. The two registries can't see each other, so
        // a const name equal to another enum's type name still collides — see the docs' known limitation.
        $constRegistry = new ImportNameRegistry;

        foreach ($this->enumFqcnMap as $fqcn => $typeName) {
            $registry->register($fqcn, $typeName);

            if (isset($this->enumConstMap[$fqcn])) {
                $constRegistry->register($fqcn, $this->enumConstMap[$fqcn]);
            }
        }

        foreach ($this->modelFqcnMap as $fqcn => $typeName) {
            if ($fqcn === $this->findable) {
                continue;
            }

            $relations = $this->modelFqcnRelations[$fqcn] ?? [];
            $preferred = count($relations) === 1
                ? Str::studly($relations[0]).$typeName
                : null;

            $registry->register($fqcn, $typeName, $preferred);
        }

        $this->applyResolvedImportNames(
            $registry->resolve(),
            $this->enumFqcnMap + $this->modelFqcnMap,
            $constRegistry->resolve(),
        );

        return $this;
    }

    /**
     * Rewrite type references in columns, mutators, and relations to use aliases.
     */
    protected function rewriteTypeReferences(): void
    {
        $nameMap = $this->enumFqcnMap + $this->modelFqcnMap;

        foreach ($this->columns as $key => $entry) {
            $this->columns[$key]['type'] = LaravelTsPublish::aliasPropertyType(
                $entry['type'], $this->columnFqcns[$key] ?? [], $nameMap, $this->importAliases,
            );
        }

        foreach ($this->mutators as $key => $entry) {
            $this->mutators[$key]['type'] = LaravelTsPublish::aliasPropertyType(
                $entry['type'], $this->mutatorFqcns[$key] ?? [], $nameMap, $this->importAliases,
            );
        }

        foreach ($this->appends as $key => $entry) {
            $this->appends[$key]['type'] = LaravelTsPublish::aliasPropertyType(
                $entry['type'], $this->appendsFqcns[$key] ?? [], $nameMap, $this->importAliases,
            );
        }

        foreach ($this->relations as $key => $entry) {
            $this->relations[$key]['type'] = LaravelTsPublish::aliasPropertyType(
                $entry['type'], $this->relationFqcns[$key] ?? [], $nameMap, $this->importAliases,
            );
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
        $hasEnums = $this->shouldGenerateHasEnums();

        $modelFqcnMap = array_filter(
            $this->modelFqcnMap,
            fn (string $typeName, string $fqcn) => $fqcn !== $this->findable,
            ARRAY_FILTER_USE_BOTH,
        );

        $typeImports = [
            ...$this->collectModularTypeImports($this->enumFqcnMap),
            ...$this->collectModularTypeImports($modelFqcnMap),
        ];

        if ($hasEnums) {
            $valueImports = $this->collectModularValueImports($this->enumPropertyFqcns());
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
     * @return array<string, string> alias => 'namespace.OriginalName'
     */
    public function globalAliasMap(): array
    {
        $map = [];

        foreach ($this->importAliases as $fqcn => $alias) {
            if (isset($this->enumFqcnMap[$fqcn])) {
                $ns = str_replace('/', '.', LaravelTsPublish::namespaceToPath($fqcn));
                $map[$alias] = $ns.'.'.$this->enumFqcnMap[$fqcn];
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
        return ['reflectionModel', 'modelInstance', 'relationNullable', 'modelInspect'];
    }

    #[Override]
    protected function enumProperties(): array
    {
        return array_merge($this->enumColumnProperties, $this->enumMutatorProperties, $this->enumAppendsProperties);
    }

    /**
     * @return array<string, EnumPropertyInfo>
     */
    protected function buildEnumColumns(): array
    {
        $result = [];

        foreach ($this->enumColumnProperties as $name => $info) {
            $result[$name] = [
                'constName' => $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']],
                'nullable' => $info['nullable'],
                'isCollection' => $info['isCollection'],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, EnumPropertyInfo>
     */
    protected function buildEnumMutators(): array
    {
        $result = [];

        foreach ($this->enumMutatorProperties as $name => $info) {
            $result[$name] = [
                'constName' => $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']],
                'nullable' => $info['nullable'],
                'isCollection' => $info['isCollection'],
            ];
        }

        return $result;
    }

    /**
     * Build the enum appends properties for the Tolki package variant.
     *
     * @return array<string, EnumPropertyInfo>
     */
    protected function buildEnumAppends(): array
    {
        $result = [];

        foreach ($this->enumAppendsProperties as $name => $info) {
            $result[$name] = [
                'constName' => $this->constImportAliases[$info['fqcn']] ?? $this->enumConstMap[$info['fqcn']],
                'nullable' => $info['nullable'],
                'isCollection' => $info['isCollection'],
            ];
        }

        return $result;
    }
}
