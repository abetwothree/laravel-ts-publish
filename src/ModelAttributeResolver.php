<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesAccessorType;
use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use Throwable;

/**
 * Centralized model attribute → TypeScript type resolver.
 *
 * Encapsulates the "accessor → cast → DB type" waterfall that was previously
 * duplicated across ResolvesModelTypes, ResourceAstAnalyzer, and ModelTransformer.
 *
 * Registered as a singleton so ModelInspector results are cached per FQCN for the
 * duration of the publish run.
 *
 * @phpstan-import-type TypeScriptTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type AttributeInfo from ModelInfo
 * @phpstan-import-type RelationInfo from ModelInfo
 */
class ModelAttributeResolver
{
    use ResolvesAccessorType;

    /**
     * Per-FQCN cache of inspected model context.
     *
     * @var array<class-string, array{
     *     instance: Model,
     *     reflection: ReflectionClass<Model>,
     *     attributes: Collection<int, AttributeInfo>,
     *     relations: Collection<int, RelationInfo>,
     *     relationNullable: RelationNullable,
     * }>
     */
    protected array $contexts = [];

    /**
     * Resolve the full TypeScriptTypeInfo for a model attribute through the
     * accessor → cast → DB type waterfall.
     *
     * @param  class-string  $modelFqcn
     * @return TypeScriptTypeInfo
     */
    public function resolveAttribute(string $modelFqcn, string $attributeName): array
    {
        $empty = LaravelTsPublish::emptyTypeScriptInfo();
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return $empty;
        }

        $attr = $ctx['attributes']->firstWhere('name', $attributeName);

        if ($attr === null) {
            return $empty;
        }

        $cast = $attr['cast'];

        // 1. Accessor — resolve via reflection to get the getter's return type
        if (($cast === 'attribute' || $cast === 'accessor')) {
            try {
                $accessorInfo = $this->resolveAccessorType($attributeName, $ctx['instance'], $ctx['reflection']);

                if ($accessorInfo['type'] !== 'unknown') {
                    return $this->appendNullable($accessorInfo, $attr['nullable']);
                }
            } catch (Throwable) { // @codeCoverageIgnore
                // Fall through to cast/DB type
            }
        }

        // 2. Regular cast (enum, date, json, etc.)
        if ($cast !== null && $cast !== '' && $cast !== 'attribute' && $cast !== 'accessor') {
            $tsInfo = LaravelTsPublish::phpToTypeScriptType($cast);

            return $this->appendNullable($tsInfo, $attr['nullable']);
        }

        // 3. DB column type
        if ($attr['type'] === null || $attr['type'] === '') {
            return $empty;
        }

        $tsInfo = LaravelTsPublish::phpToTypeScriptType($attr['type']);

        if ($tsInfo['type'] === 'unknown') {
            return $empty; // @codeCoverageIgnore
        }

        return $this->appendNullable($tsInfo, $attr['nullable']);
    }

    /**
     * Resolve a relation name to its TypeScript type and related model FQCN.
     *
     * @param  class-string  $modelFqcn
     * @return array{type: string, modelFqcn: class-string<Model>|null}
     */
    public function resolveRelation(string $modelFqcn, string $relationName): array
    {
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        $relation = $ctx['relations']->firstWhere('name', $relationName);

        if ($relation === null) {
            return ['type' => 'unknown', 'modelFqcn' => null];
        }

        $relatedModel = class_basename($relation['related']);
        $containsMany = str_contains(strtolower($relation['type']), 'many');

        if ($containsMany) {
            return ['type' => $relatedModel.'[]', 'modelFqcn' => $relation['related']];
        }

        $type = $relatedModel;
        $nullableRelations = config()->boolean('ts-publish.nullable_relations');

        if ($nullableRelations && $ctx['relationNullable']->isNullable($relation)) {
            $type .= ' | null';
        }

        return ['type' => $type, 'modelFqcn' => $relation['related']];
    }

    /**
     * Resolve the return type of a method (instance or static) on a model.
     *
     * @param  class-string  $modelFqcn
     * @return TypeScriptTypeInfo
     */
    public function resolveMethodReturnType(string $modelFqcn, string $methodName): array
    {
        /** @var ReflectionClass<Model> $reflection */
        $reflection = new ReflectionClass($modelFqcn);

        return LaravelTsPublish::methodOrDocblockReturnTypes($reflection, $methodName);
    }

    /**
     * If an attribute is an accessor whose getter returns exactly one Eloquent
     * Model subclass, return its FQCN. Used as a fallback when the property is
     * not a database relation.
     *
     * @param  class-string  $modelFqcn
     * @return class-string<Model>|null
     */
    public function resolveAccessorModelFqcn(string $modelFqcn, string $attributeName): ?string
    {
        $ctx = $this->resolveContext($modelFqcn);

        if ($ctx === null) {
            return null;
        }

        $attr = $ctx['attributes']->firstWhere('name', $attributeName);

        if ($attr === null || ($attr['cast'] !== 'attribute' && $attr['cast'] !== 'accessor')) {
            return null; // @codeCoverageIgnore
        }

        try {
            $accessorInfo = $this->resolveAccessorType($attributeName, $ctx['instance'], $ctx['reflection']);

            $modelFqcns = array_values(array_filter(
                $accessorInfo['classFqcns'],
                fn (string $fqcn) => is_a($fqcn, Model::class, true),
            ));

            if (count($modelFqcns) === 0) {
                return null;
            }

            /** @var class-string<Model> $fqcn */
            $fqcn = $modelFqcns[0];

            return $fqcn;
        } catch (Throwable) { // @codeCoverageIgnore
            return null; // @codeCoverageIgnore
        }
    }

    /**
     * @param  class-string  $modelFqcn
     * @return Collection<int, AttributeInfo>|null
     */
    public function getAttributes(string $modelFqcn): ?Collection
    {
        return $this->resolveContext($modelFqcn)['attributes'] ?? null;
    }

    /**
     * @param  class-string  $modelFqcn
     * @return Collection<int, RelationInfo>|null
     */
    public function getRelations(string $modelFqcn): ?Collection
    {
        return $this->resolveContext($modelFqcn)['relations'] ?? null;
    }

    /**
     * @param  class-string  $modelFqcn
     */
    public function getRelationNullable(string $modelFqcn): ?RelationNullable
    {
        return $this->resolveContext($modelFqcn)['relationNullable'] ?? null;
    }

    /**
     * @param  class-string  $modelFqcn
     */
    public function getInstance(string $modelFqcn): ?Model
    {
        return $this->resolveContext($modelFqcn)['instance'] ?? null;
    }

    /**
     * @param  class-string  $modelFqcn
     * @return ReflectionClass<Model>|null
     */
    public function getReflection(string $modelFqcn): ?ReflectionClass
    {
        return $this->resolveContext($modelFqcn)['reflection'] ?? null;
    }

    /**
     * Lazily build and cache the model context (instance, reflection, attributes, relations).
     *
     * @param  class-string  $modelFqcn
     * @return array{instance: Model, reflection: ReflectionClass<Model>, attributes: Collection<int, AttributeInfo>, relations: Collection<int, RelationInfo>, relationNullable: RelationNullable}|null
     */
    protected function resolveContext(string $modelFqcn): ?array
    {
        if (isset($this->contexts[$modelFqcn])) {
            return $this->contexts[$modelFqcn];
        }

        if (! class_exists($modelFqcn)) {
            return null;
        }

        try {
            /** @var Model $instance */
            $instance = resolve($modelFqcn);

            $data = resolve(ModelInspector::class)->inspect($modelFqcn);

            /** @var Collection<int, AttributeInfo> $attributes */
            $attributes = $data->attributes;

            /** @var ReflectionClass<Model> $reflection */
            $reflection = new ReflectionClass($modelFqcn);

            $this->contexts[$modelFqcn] = [
                'instance' => $instance,
                'reflection' => $reflection,
                'attributes' => $attributes,
                'relations' => $data->relations,
                'relationNullable' => new RelationNullable($instance, $attributes),
            ];

            return $this->contexts[$modelFqcn];
        } catch (Throwable) { // @codeCoverageIgnore
            return null; // @codeCoverageIgnore
        }
    }

    /**
     * Append ' | null' to a TypeScriptTypeInfo's type when nullable and not already present.
     *
     * @param  TypeScriptTypeInfo  $tsInfo
     * @return TypeScriptTypeInfo
     */
    protected function appendNullable(array $tsInfo, ?bool $nullable): array
    {
        if ($nullable && ! str_contains($tsInfo['type'], 'null')) {
            $tsInfo['type'] .= ' | null';
        }

        return $tsInfo;
    }
}
