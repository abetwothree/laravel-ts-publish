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
 * @phpstan-type ModelImportList = list<string>
 * @phpstan-type EnumImportList = list<string>
 * @phpstan-type TsTypeOverrides = array<string, string>
 * @phpstan-type ModelData = array{
 *    modelName: string,
 *    filePath: string,
 *    columns: ColumnsList,
 *    enumImports: EnumImportList,
 *    modelImports: ModelImportList,
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

    /** @var ModelImportList */
    public protected(set) array $modelImports = [];

    /** @var EnumImportList */
    public protected(set) array $enumImports = [];

    /** @var TsTypeOverrides */
    public protected(set) array $tsTypeOverrides = [];

    #[Override]
    public function transform(): self
    {
        $this->initInstance()
            ->parseTsTypeOverrides()
            ->transformColumns()
            ->transformMutators()
            ->transformRelations();

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
        $modelImports = array_values(array_filter(
            array_unique($this->modelImports),
            fn (string $import) => $import !== $this->modelName,
        ));

        return [
            'modelName' => $this->modelName,
            'filePath' => $this->filePath,
            'columns' => $this->columns,
            'mutators' => $this->mutators,
            'relations' => $this->relations,
            'modelImports' => $modelImports,
            'enumImports' => array_values(array_unique($this->enumImports)),
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
        $this->tsTypeOverrides = array_merge($classOverrides, $propertyOverrides, $methodOverrides);

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

            $typings = LaravelTsPublish::phpToTypeScriptType($attribute['cast'] ?? $attribute['type']);

            $type = $typings['type'];

            if ($attribute['nullable'] && ! str_contains($type, 'null')) {
                $type .= ' | null';
            }

            $this->columns[$name] = $type;
            $this->enumImports = [...$this->enumImports, ...$typings['enumTypes']];
            $this->modelImports = [...$this->modelImports, ...$typings['classes']];
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
            $this->enumImports = [...$this->enumImports, ...$resolved['enumTypes']];
            $this->modelImports = [...$this->modelImports, ...$resolved['classes']];
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

            $relationName = LaravelTsPublish::keyCase($relation['name']);
            $this->relations[$relationName] = $relationType;
            $this->modelImports[] = $relatedBasename;
        }

        return $this;
    }

    /** @return TypeScriptTypeInfo */
    private function resolveMutatorType(string $name): array
    {
        $result = LaravelTsPublish::emptyTypeScriptInfo();
        $newStyle = Str::camel($name);
        $oldStyle = 'get'.Str::studly($name).'Attribute';

        // New-style: protected function titleDisplay(): Attribute
        // Must invoke via reflection because the method is protected
        if ($this->reflectionModel->hasMethod($newStyle)) {
            $method = $this->reflectionModel->getMethod($newStyle);
            $method->setAccessible(true);

            /** @var Attribute<mixed, mixed> $attrInstance */
            $attrInstance = $method->invoke($this->modelInstance);

            if ($attrInstance->get !== null) {
                /** @var \Closure $getter */
                $getter = $attrInstance->get;

                return LaravelTsPublish::closureReturnedTypes($getter);
            }

            // write-only mutator (set only, no get) — not readable on the model shape
            return $result;
        }

        // Old-style: public function getTitleDisplayAttribute($value): string
        if ($this->reflectionModel->hasMethod($oldStyle)) {
            return LaravelTsPublish::methodReturnedTypes($this->reflectionModel, $oldStyle);
        }

        return $result;
    }
}
