<?php

namespace AbeTwoThree\LaravelTsPublish\Transformers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;

/**
 * @phpstan-import-type TypeScriptTypeInfo from LaravelTsPublish
 *
 * @extends CoreTransformer<Model>
 */
class ModelTransformer extends CoreTransformer
{
    protected Model $modelInstance;

    protected ReflectionClass $reflectionModel;

    protected array $dbColumns = [];

    protected array $modelInspect;

    /** @var list<string> */
    protected array $columns = [];

    /** @var list<string> */
    protected array $mutators = [];

    /** @var list<string> */
    protected array $relations = [];

    /** @var list<string> */
    protected array $modelImports = []; // ['User', 'Comment'] → import from './index.d.ts'

    /** @var list<string> */
    protected array $enumImports = []; // ['StatusType'] → import from '../enums'

    /** @var array<string, string> */
    protected array $tsTypeOverrides = []; // column name → raw TS type string from #[TsType]

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
     * @return array{
     *    modelName: string,
     *    columns: string[],
     *    enumImports: array,
     *    modelImports: array,
     *    mutators: string[],
     *    relations: string[],
     * }
     */
    #[Override]
    public function data(): array
    {
        $modelName = $this->reflectionModel->getShortName();
        $modelImports = array_values(array_filter(
            array_unique($this->modelImports),
            fn ($import) => $import !== $modelName,
        ));

        return [
            'modelName' => $modelName,
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
        return Str::kebab($this->reflectionModel->getShortName());
    }

    protected function initInstance(): self
    {
        $this->modelInstance = resolve($this->findable);
        $this->dbColumns = $this->modelInstance->getConnection()->getSchemaBuilder()->getColumnListing($this->modelInstance->getTable());
        $this->modelInspect = resolve(ModelInspector::class)->inspect($this->findable);
        $this->reflectionModel = new ReflectionClass($this->findable);

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
            if (is_array($instance->types)) {
                $classOverrides = array_merge($classOverrides, $instance->types);
            }
        }

        // $casts property (older style)
        if ($this->reflectionModel->hasProperty('casts')) {
            foreach ($this->reflectionModel->getProperty('casts')->getAttributes(TsCasts::class) as $attr) {
                $instance = $attr->newInstance();
                if (is_array($instance->types)) {
                    $propertyOverrides = array_merge($propertyOverrides, $instance->types);
                }
            }
        }

        // casts() method (Laravel 9+ style)
        if ($this->reflectionModel->hasMethod('casts')) {
            foreach ($this->reflectionModel->getMethod('casts')->getAttributes(TsCasts::class) as $attr) {
                $instance = $attr->newInstance();
                if (is_array($instance->types)) {
                    $methodOverrides = array_merge($methodOverrides, $instance->types);
                }
            }
        }

        // Method wins over property wins over class, matching Laravel's own cast resolution
        $this->tsTypeOverrides = array_merge($classOverrides, $propertyOverrides, $methodOverrides);

        return $this;
    }

    protected function transformColumns(): self
    {
        $attributes = $this->modelInspect['attributes']->filter(fn ($attr) => in_array($attr['name'], $this->dbColumns));

        foreach ($attributes as $attribute) {
            // #[TsCasts] override takes priority over automatic type resolution
            if (isset($this->tsTypeOverrides[$attribute['name']])) {
                $this->columns[$attribute['name']] = $this->tsTypeOverrides[$attribute['name']];

                continue;
            }

            $typings = LaravelTsPublish::phpToTypeScriptType($attribute['cast'] ?? $attribute['type']);

            $type = is_callable($typings['type'])
                ? call_user_func($typings['type'])
                : $typings['type'];

            if ($attribute['nullable'] && ! str_contains($type, 'null')) {
                $type .= ' | null';
            }

            $this->columns[$attribute['name']] = $type;
            $this->enumImports = [...$this->enumImports,  ...($typings['enumTypes'] ?? [])];
            $this->modelImports = [...$this->modelImports, ...$typings['classes']];
        }

        return $this;
    }

    protected function transformMutators(): self
    {
        $mutators = $this->modelInspect['attributes']->filter(fn ($attr) => ! in_array($attr['name'], $this->dbColumns));

        foreach ($mutators as $mutator) {
            // #[TsCasts] override takes priority
            if (isset($this->tsTypeOverrides[$mutator['name']])) {
                $this->mutators[$mutator['name']] = $this->tsTypeOverrides[$mutator['name']];

                continue;
            }

            $resolved = $this->resolveMutatorType($mutator['name']);
            $this->mutators[$mutator['name']] = $resolved['type'];
            $this->enumImports = [...$this->enumImports,  ...($resolved['enumTypes'] ?? [])];
            $this->modelImports = [...$this->modelImports, ...$resolved['classes']];
        }

        return $this;
    }

    protected function transformRelations(): self
    {
        $relations = $this->modelInspect['relations']
            ->when(
                config()->array('ts-publish.included_models', []),
                fn ($relations, $includedModels) => $relations->filter(fn (array $relation) => in_array($relation['related'], $includedModels))
            )
            ->when(
                config()->array('ts-publish.excluded_models', []),
                fn ($relations, $excludedModels) => $relations->filter(fn (array $relation) => ! in_array($relation['related'], $excludedModels))
            );

        foreach ($relations as $relation) {
            $relatedBasename = class_basename($relation['related']);
            $this->relations[$relation['name']] = $relatedBasename;
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
            $attrInstance = $method->invoke($this->modelInstance);

            if ($attrInstance->get !== null) {
                return LaravelTsPublish::closureReturnedTypes($attrInstance->get) ?: $result;
            }

            // write-only mutator (set only, no get) — not readable on the model shape
            return $result;
        }

        // Old-style: public function getTitleDisplayAttribute($value): string
        if ($this->reflectionModel->hasMethod($oldStyle)) {
            return LaravelTsPublish::methodReturnedTypes($this->reflectionModel, $oldStyle) ?: $result;
        }

        return $result;
    }
}
