<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\RelationNullable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;

/**
 * Model type resolution helpers for the ResourceAstAnalyzer.
 *
 * Delegates the "accessor → cast → DB type" waterfall to ModelAttributeResolver
 * and provides thin wrappers that preserve the return shapes expected by callers.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 * @phpstan-import-type AttributeInfo from \AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo
 * @phpstan-import-type RelationInfo from \AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo
 *
 * @phpstan-type ModelAttributeTypeResult = array{type: string, enumFqcn: class-string|null}
 * @phpstan-type ModelRelationTypeResult = array{type: string, modelFqcn: class-string<\Illuminate\Database\Eloquent\Model>|null}
 */
trait ResolvesModelTypes
{
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

        $resolver = resolve(ModelAttributeResolver::class);

        $this->modelAttributes = $resolver->getAttributes($this->modelClass);
        $this->modelRelations = $resolver->getRelations($this->modelClass);
        $this->modelInstance = $resolver->getInstance($this->modelClass);
        $this->modelReflection = $resolver->getReflection($this->modelClass);
        $this->relationNullable = $resolver->getRelationNullable($this->modelClass);
    }

    /**
     * Resolve the TypeScript type and optional enum FQCN for a model attribute.
     *
     * @return ModelAttributeTypeResult
     */
    protected function resolveModelAttributeTypeInfo(string $attributeName): array
    {
        if ($this->modelClass === null || $this->modelAttributes === null) {
            return ['type' => 'unknown', 'enumFqcn' => null];
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($this->modelClass, $attributeName);

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return ['type' => $tsInfo['type'], 'enumFqcn' => $enumFqcn];
    }

    /**
     * @return ModelRelationTypeResult
     */
    protected function resolveModelRelationTypeInfo(string $relationName): array
    {
        if ($this->modelClass === null || $this->modelRelations === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        return resolve(ModelAttributeResolver::class)->resolveRelation($this->modelClass, $relationName);
    }

    /**
     * If $propName is an accessor attribute whose getter returns exactly one Eloquent Model
     * subclass, return its FQCN. Used by analyzeRelationFilter() as a fallback when the
     * property is not a database relation.
     *
     * @return class-string<Model>|null
     */
    protected function resolveAccessorModelFqcn(string $propName): ?string
    {
        if ($this->modelClass === null) {
            return null; // @codeCoverageIgnore
        }

        return resolve(ModelAttributeResolver::class)->resolveAccessorModelFqcn($this->modelClass, $propName);
    }

    /**
     * Return all Eloquent Model FQCNs that an accessor returns.
     * Used by analyzeRelationFilter() when the accessor union-types multiple models.
     *
     * @return list<class-string<Model>>
     */
    protected function resolveAccessorModelFqcns(string $propName): array
    {
        if ($this->modelClass === null) {
            return []; // @codeCoverageIgnore
        }

        return resolve(ModelAttributeResolver::class)->resolveAccessorModelFqcns($this->modelClass, $propName);
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
        $resolver = resolve(ModelAttributeResolver::class);

        $relatedAttributes = $resolver->getAttributes($relatedModelClass);
        $relatedRelations = $resolver->getRelations($relatedModelClass);

        if ($relatedAttributes === null || $relatedRelations === null) {
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
                $tsInfo = $resolver->resolveAttribute($relatedModelClass, $key);

                if ($tsInfo['type'] !== 'unknown') {
                    $parts[] = $key.': '.$tsInfo['type'];

                    /** @var list<class-string> $enumFqcns */
                    $enumFqcns = $tsInfo['enumFqcns'];
                    array_push($collectedEnumFqcns, ...$enumFqcns);
                }

                continue;
            }

            // Relation
            $relationInfo = $resolver->resolveRelation($relatedModelClass, $key);

            if ($relationInfo['type'] !== 'unknown') {
                $parts[] = $key.': '.$relationInfo['type'];

                if ($relationInfo['modelFqcn'] !== null) {
                    /** @var class-string $relatedFqcn */
                    $relatedFqcn = $relationInfo['modelFqcn'];
                    $collectedModelFqcns[] = $relatedFqcn;
                }
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
