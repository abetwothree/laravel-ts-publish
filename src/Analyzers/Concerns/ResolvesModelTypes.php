<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesFilteredRelationTypes;
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
 * Requires the host to expose `AnalysisScope $scope`.
 *
 * @phpstan-import-type AttributeInfo from \AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo
 * @phpstan-import-type RelationInfo from \AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo
 *
 * @phpstan-type ModelAttributeTypeResult = array{type: string, enumFqcn: class-string|null, classFqcns: list<class-string>}
 * @phpstan-type ModelRelationTypeResult = array{type: string, modelFqcn: class-string<\Illuminate\Database\Eloquent\Model>|null, morphFqcns: list<class-string>}
 */
trait ResolvesModelTypes
{
    use ResolvesFilteredRelationTypes;

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
        if ($this->scope->modelClass === null || ! class_exists($this->scope->modelClass)) {
            return;
        }

        $resolver = resolve(ModelAttributeResolver::class);

        $this->modelAttributes = $resolver->getAttributes($this->scope->modelClass);
        $this->modelRelations = $resolver->getRelations($this->scope->modelClass);
        $this->modelInstance = $resolver->getInstance($this->scope->modelClass);
        $this->modelReflection = $resolver->getReflection($this->scope->modelClass);
        $this->relationNullable = $resolver->getRelationNullable($this->scope->modelClass);
    }

    /**
     * Resolve the TypeScript type, optional enum FQCN, and any class FQCNs for a model attribute.
     *
     * @return ModelAttributeTypeResult
     */
    protected function resolveModelAttributeTypeInfo(string $attributeName): array
    {
        if ($this->scope->modelClass === null || $this->modelAttributes === null) {
            return ['type' => 'unknown', 'enumFqcn' => null, 'classFqcns' => []];
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($this->scope->modelClass, $attributeName);

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return ['type' => $tsInfo['type'], 'enumFqcn' => $enumFqcn, 'classFqcns' => $tsInfo['classFqcns']];
    }

    /**
     * Build a ResourceAnalysis from all model attributes and relations when the resource
     * delegates to JsonResource::toArray(). $excludeHidden is false only for only(), whose
     * property set is the caller's own keys — also gates the write-only mutator skip below.
     */
    protected function buildModelDelegatedAnalysis(bool $excludeHidden = true): ?ResourceAnalysis
    {
        if ($this->modelAttributes === null || $this->scope->modelClass === null) {
            return null;
        }

        /** @var class-string $modelClass */
        $modelClass = $this->scope->modelClass;
        $resolver = resolve(ModelAttributeResolver::class);
        $dropHidden = $excludeHidden && $resolver->excludeHiddenAttributes();
        $dbColumns = $resolver->databaseColumnNames($modelClass);

        $analysis = new ResourceAnalysis;

        foreach ($this->modelAttributes as $attr) {
            if ($dropHidden && $attr['hidden']) {
                continue;
            }

            // Only the implicit paths (except()/whole-model) may drop a write-only mutator: Model::only()
            // resolves through getAttribute(), which does return the key, so a named one must survive.
            $isOmittedMutator = $excludeHidden
                && ! in_array($attr['name'], $dbColumns, true)
                && $resolver->isOmittedMutator($modelClass, $attr['name']);

            if ($isOmittedMutator) {
                continue;
            }

            $info = $this->resolveModelAttributeTypeInfo($attr['name']);

            $analysis->addProperty($attr['name'], [
                'type' => $info['type'],
                'optional' => false,
                ...($info['enumFqcn'] !== null ? ['directEnumFqcn' => $info['enumFqcn']] : []),
            ]);
        }

        // Also include relations so they can be referenced by only()/except() filters
        if ($this->modelRelations !== null) {
            foreach ($this->modelRelations as $relation) {
                $info = $this->resolveModelRelationTypeInfo($relation['name'], $this->scope);

                if ($info['type'] !== 'unknown') {
                    // Morph arms travel as embedded FQCNs: self-keyed in modelFqcns so every arm of a
                    // MorphTo union is imported, and queued per property so ResourceTransformer can
                    // alias same-basename parents apart.
                    $analysis->addProperty($relation['name'], [
                        'type' => $info['type'],
                        'optional' => false,
                        ...($info['modelFqcn'] !== null ? ['modelFqcn' => $info['modelFqcn']] : []),
                        'embeddedModelFqcns' => $info['morphFqcns'],
                    ]);
                }
            }
        }

        return $analysis;
    }
}
