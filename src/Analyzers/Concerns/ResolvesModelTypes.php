<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesAccessorType;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelInspector;
use AbeTwoThree\LaravelTsPublish\RelationNullable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;

/**
 * Model type resolution helpers for the ResourceAstAnalyzer.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 * @phpstan-import-type AttributeInfo from \AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo
 * @phpstan-import-type RelationInfo from \AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo
 *
 * @phpstan-type ModelAttributeTypeResult = array{type: string, enumFqcn: class-string|null}
 * @phpstan-type ModelRelationTypeResult = array{type: string, modelFqcn: class-string|null}
 */
trait ResolvesModelTypes
{
    use ResolvesAccessorType;

    protected ?Model $modelInstance = null;

    protected ?RelationNullable $relationNullable = null;

    /** @var ReflectionClass<Model>|null */
    protected ?ReflectionClass $modelReflection = null;

    /** @var Collection<int, AttributeInfo>|null */
    protected ?Collection $modelAttributes = null;

    /** @var Collection<int, RelationInfo>|null */
    protected ?Collection $modelRelations = null;

    protected function loadModelInspectorData(): void
    {
        if ($this->modelClass === null || ! class_exists($this->modelClass)) {
            return;
        }

        try {
            /** @var Model $modelInstance */
            $modelInstance = resolve($this->modelClass);
            $this->modelInstance = $modelInstance;

            $data = resolve(ModelInspector::class)->inspect($this->modelClass);
            /** @var Collection<int, AttributeInfo> $attributes */
            $attributes = $data->attributes;
            $this->modelAttributes = $attributes;
            $this->modelRelations = $data->relations;
            $this->modelReflection = new ReflectionClass($this->modelClass);
            $this->relationNullable = new RelationNullable($this->modelInstance, $this->modelAttributes);
        } catch (\Throwable) { // @codeCoverageIgnore
            // Model may not have a working database connection during analysis
        }
    }

    /**
     * Resolve the TypeScript type and optional enum FQCN for a model attribute.
     *
     * @return ModelAttributeTypeResult
     */
    protected function resolveModelAttributeTypeInfo(string $attributeName): array
    {
        if ($this->modelAttributes === null) {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        $attr = $this->modelAttributes->firstWhere('name', $attributeName);

        if ($attr === null) {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        $cast = $attr['cast'];

        // Accessor columns — resolve via reflection to get the accessor's return type
        if (($cast === 'attribute' || $cast === 'accessor') && $this->modelInstance !== null && $this->modelReflection !== null) {
            try {
                $accessorInfo = $this->resolveAccessorType($attributeName, $this->modelInstance, $this->modelReflection);

                if ($accessorInfo['type'] !== 'unknown') {
                    $type = $accessorInfo['type'];

                    if ($attr['nullable'] && ! str_contains($type, 'null')) {
                        $type .= ' | null';
                    }

                    /** @var class-string|null $enumFqcn */
                    $enumFqcn = $accessorInfo['enumFqcns'][0] ?? null;

                    return ['type' => $type, 'enumFqcn' => $enumFqcn];
                }
            } catch (\Throwable) { // @codeCoverageIgnore
                // Accessor may fail without full runtime context — fall through to DB type
            }
        }

        // Regular casts (enum, date, json, etc.)
        if ($cast !== null && $cast !== '' && $cast !== 'attribute' && $cast !== 'accessor') {
            $tsInfo = LaravelTsPublish::toTsType($cast);
            $type = $tsInfo['type'];

            if ($attr['nullable'] && ! str_contains($type, 'null')) {
                $type .= ' | null';
            }

            /** @var class-string|null $enumFqcn */
            $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

            return ['type' => $type, 'enumFqcn' => $enumFqcn];
        }

        // Fall back to DB column type
        if ($attr['type'] === null || $attr['type'] === '') {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        $tsInfo = LaravelTsPublish::toTsType($attr['type']);
        $type = $tsInfo['type'];

        if ($attr['nullable'] && $type !== 'unknown') {
            $type .= ' | null';
        }

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return ['type' => $type, 'enumFqcn' => $enumFqcn];
    }

    /**
     * @return ModelRelationTypeResult
     */
    protected function resolveModelRelationTypeInfo(string $relationName): array
    {
        if ($this->modelRelations === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        $relation = $this->modelRelations->firstWhere('name', $relationName);

        if ($relation === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        $relatedModel = class_basename($relation['related']);
        $containsMany = str_contains(strtolower($relation['type']), 'many');

        if ($containsMany) {
            return ['type' => $relatedModel.'[]', 'modelFqcn' => $relation['related']];
        }

        $type = $relatedModel;
        $nullableRelations = config()->boolean('ts-publish.models.nullable_relations');

        if ($nullableRelations && $this->relationNullable?->isNullable($relation)) {
            $type .= ' | null';
        }

        return ['type' => $type, 'modelFqcn' => $relation['related']];
    }

    /**
     * If $propName is an accessor attribute whose getter returns exactly one Eloquent Model
     * subclass, return its FQCN. Used by analyzeRelationFilter() as a fallback when the
     * property is not a database relation.
     *
     * Returns null when:
     * - The attribute is not an accessor.
     * - No Model subclass is found in the return type.
     * - Two or more Model subclasses are found (ambiguous union, e.g. Payment|Invoice).
     *
     * @return class-string<Model>|null
     */
    protected function resolveAccessorModelFqcn(string $propName): ?string
    {
        if ($this->modelAttributes === null || $this->modelInstance === null || $this->modelReflection === null) {
            return null; // @codeCoverageIgnore
        }

        $attr = $this->modelAttributes->firstWhere('name', $propName);

        if ($attr === null || ($attr['cast'] !== 'attribute' && $attr['cast'] !== 'accessor')) {
            return null; // @codeCoverageIgnore
        }

        try {
            $accessorInfo = $this->resolveAccessorType($propName, $this->modelInstance, $this->modelReflection);

            // Filter to actual Eloquent Model subclasses only.
            // Non-model classes (enums, value objects) are excluded.
            // Two+ matches means an ambiguous union — fall back to unknown.
            $modelFqcns = array_values(array_filter(
                $accessorInfo['classFqcns'],
                fn (string $fqcn) => is_a($fqcn, Model::class, true),
            ));

            if (count($modelFqcns) === 0) {
                return null; // @codeCoverageIgnore
            }

            /** @var class-string<Model> $fqcn */
            $fqcn = $modelFqcns[0];

            return $fqcn;
        } catch (\Throwable) { // @codeCoverageIgnore
            return null; // @codeCoverageIgnore
        }
    }

    /**
     * Build a ResourceAnalysis from all model attributes and relations when the resource
     * delegates to JsonResource::toArray() (which returns $this->resource->toArray()).
     */
    protected function buildModelDelegatedAnalysis(): ?ResourceAnalysis
    {
        if ($this->modelAttributes === null) {
            return null;
        }

        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];

        foreach ($this->modelAttributes as $attr) {
            $info = $this->resolveModelAttributeTypeInfo($attr['name']);

            $properties[] = [
                'name' => $attr['name'],
                'type' => $info['type'],
                'optional' => false,
                'description' => '',
            ];

            if ($info['enumFqcn'] !== null) {
                $directEnumFqcns[$attr['name']] = $info['enumFqcn'];
            }
        }

        // Also include relations so they can be referenced by only()/except() filters
        if ($this->modelRelations !== null) {
            foreach ($this->modelRelations as $relation) {
                $info = $this->resolveModelRelationTypeInfo($relation['name']);

                if ($info['type'] !== 'unknown') {
                    $properties[] = [
                        'name' => $relation['name'],
                        'type' => $info['type'],
                        'optional' => false,
                        'description' => '',
                    ];

                    if ($info['modelFqcn'] !== null) {
                        $modelFqcns[$relation['name']] = $info['modelFqcn'];
                    }
                }
            }
        }

        return new ResourceAnalysis(
            properties: $properties,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
        );
    }

    /**
     * Resolve an inline TypeScript type for a filtered subset of a related model's attributes and relations.
     *
     * Used when a resource accesses `$this->relation->only([...])` or `->except([...])`.
     * Returns an array with the inline type string (`{ id: number; name: string }` or `'unknown'`)
     * plus collected enum and model FQCNs so the caller can feed them into the import pipeline.
     *
     * @param  class-string  $relatedModelClass
     * @param  list<string>  $keys
     * @return array{type: string, enumFqcns: list<class-string>, modelFqcns: list<class-string>}
     */
    protected function resolveFilteredRelationType(
        string $relatedModelClass,
        array $keys,
        bool $include,
    ): array {
        $result = ['type' => 'unknown', 'enumFqcns' => [], 'modelFqcns' => []];

        try {
            /** @var Model $relatedInstance */
            $relatedInstance = resolve($relatedModelClass);
            $data = resolve(ModelInspector::class)->inspect($relatedModelClass);
            /** @var Collection<int, array{name: string, type: string|null, cast: string|null, nullable: bool}> $relatedAttributes */
            $relatedAttributes = $data->attributes;
            $relatedRelations = $data->relations;
            /** @var ReflectionClass<Model> $relatedReflection */
            $relatedReflection = new ReflectionClass($relatedModelClass);
        } catch (\Throwable) { // @codeCoverageIgnore
            return $result; // @codeCoverageIgnore
        }

        if ($include) {
            $resolveKeys = $keys;
        } else {
            $attrNames = $relatedAttributes->pluck('name')->all();
            $relationNames = $relatedRelations->pluck('name')->all();
            $resolveKeys = array_values(array_filter(
                array_merge($attrNames, $relationNames),
                fn (mixed $k) => ! in_array($k, $keys, true),
            ));
        }

        $parts = [];
        /** @var list<class-string> $collectedEnumFqcns */
        $collectedEnumFqcns = [];
        /** @var list<class-string> $collectedModelFqcns */
        $collectedModelFqcns = [];

        /** @var list<string> $resolveKeys */
        foreach ($resolveKeys as $key) {
            $attr = $relatedAttributes->firstWhere('name', $key);

            if ($attr !== null) {
                // Accessor: resolve via reflection on the related model
                if ($attr['cast'] === 'attribute' || $attr['cast'] === 'accessor') {
                    try {
                        $accessorInfo = $this->resolveAccessorType($key, $relatedInstance, $relatedReflection);

                        if ($accessorInfo['type'] !== 'unknown') {
                            $type = $accessorInfo['type'];

                            if ($attr['nullable'] && ! str_contains($type, 'null')) {
                                $type .= ' | null';
                            }

                            $parts[] = $key.': '.$type;
                            /** @var list<class-string> $accessorEnumFqcns */
                            $accessorEnumFqcns = $accessorInfo['enumFqcns'];
                            array_push($collectedEnumFqcns, ...$accessorEnumFqcns);

                            continue;
                        }
                    } catch (\Throwable) { // @codeCoverageIgnore
                        // Fall through to cast/type resolution
                    }
                }

                // Regular cast (enum, date, json, etc.)
                if ($attr['cast'] !== null && $attr['cast'] !== '' && $attr['cast'] !== 'attribute' && $attr['cast'] !== 'accessor') {
                    $tsInfo = LaravelTsPublish::toTsType($attr['cast']);
                    $type = $tsInfo['type'];

                    if ($attr['nullable'] && ! str_contains($type, 'null')) {
                        $type .= ' | null';
                    }

                    $parts[] = $key.': '.$type;
                    /** @var list<class-string> $castEnumFqcns */
                    $castEnumFqcns = $tsInfo['enumFqcns'];
                    array_push($collectedEnumFqcns, ...$castEnumFqcns);

                    continue;
                }

                // DB column type
                if ($attr['type'] !== null && $attr['type'] !== '') {
                    $tsInfo = LaravelTsPublish::toTsType($attr['type']);
                    $type = $tsInfo['type'];

                    if ($attr['nullable'] && $type !== 'unknown') {
                        $type .= ' | null';
                    }

                    if ($type !== 'unknown') {
                        $parts[] = $key.': '.$type;
                        /** @var list<class-string> $colEnumFqcns */
                        $colEnumFqcns = $tsInfo['enumFqcns'];
                        array_push($collectedEnumFqcns, ...$colEnumFqcns);
                    }
                }

                continue;
            }

            // Relation
            $relation = $relatedRelations->firstWhere('name', $key);

            if ($relation !== null) {
                $relatedName = class_basename($relation['related']);
                $containsMany = str_contains(strtolower($relation['type']), 'many');
                $parts[] = $key.': '.($containsMany ? $relatedName.'[]' : $relatedName);
                /** @var class-string $relatedFqcn */
                $relatedFqcn = $relation['related'];
                $collectedModelFqcns[] = $relatedFqcn;
            }
        }

        $inlineType = $parts === [] ? 'unknown' : '{ '.implode('; ', $parts).' }';

        return [
            ...$result,
            'type' => $inlineType,
            'enumFqcns' => $collectedEnumFqcns,
            'modelFqcns' => $collectedModelFqcns,
        ];
    }
}
