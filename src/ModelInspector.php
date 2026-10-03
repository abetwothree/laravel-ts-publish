<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;
use AbeTwoThree\LaravelTsPublish\Support\AnalysisWarnings;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector as EloquentModelInspector;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileObject;
use Throwable;

/**
 * @phpstan-import-type RelationInfo from ModelInfo
 */
class ModelInspector extends EloquentModelInspector
{
    /**
     * @param  class-string<Model>|string  $model
     * @return ModelInfo<Model>
     *
     * @phpstan-ignore method.childReturnType
     */
    #[Override]
    public function inspect($model, $connection = null): Arrayable
    {
        /** @var array<string, mixed>|Arrayable<string, mixed> $modelInfo */
        $modelInfo = parent::inspect($model, $connection);

        if ($modelInfo instanceof Arrayable) {
            /** @var array<string, mixed> $data */
            $data = $modelInfo->toArray();
            $modelInfo = $data;
        }

        /**
         * @var array{
         *  class: class-string<Model>,
         *  database: string,
         *  table: string,
         *  policy: class-string|null,
         *  attributes: Collection<int, array{name: string, type: string|null, cast: string|null, nullable: bool, hidden: bool}>,
         *  relations: Collection<int, array{name: string, type: string, related: class-string<Model>}>,
         *  events: Collection<int, array{event: string, class: string}>,
         *  observers: Collection<int, array{event: string, observer: array<int, string>}>,
         *  collection: class-string<\Illuminate\Database\Eloquent\Collection<int, Model>>,
         *  builder: class-string<Builder<Model>>,
         *  resource: class-string<JsonResource>|null
         *  } $modelInfo
         */
        return new ModelInfo(
            class: $modelInfo['class'],
            database: $modelInfo['database'],
            table: $modelInfo['table'],
            policy: $modelInfo['policy'],
            attributes: $modelInfo['attributes'],
            relations: $modelInfo['relations'],
            events: $modelInfo['events'],
            observers: $modelInfo['observers'],
            collection: $modelInfo['collection'],
            builder: $modelInfo['builder'],
            resource: $modelInfo['resource'] ?? null,
        );
    }

    /**
     * Reads the relations from the model's methods, without its table.
     *
     * @return Collection<int, RelationInfo>
     */
    public function relationsOf(Model $model): Collection
    {
        /** @var Collection<int, RelationInfo> $relations */
        $relations = $this->getRelations($model);

        return $relations;
    }

    /**
     * Laravel's relation list, unless a relation method throws on a blank model: then that relation alone is left out.
     *
     * @param  Model  $model
     * @return Collection<int, RelationInfo>
     */
    #[Override]
    protected function getRelations($model): Collection
    {
        try {
            /** @var Collection<int, RelationInfo> $relations */
            $relations = parent::getRelations($model);

            return $relations;
        } catch (Throwable) {
            // A fresh instance, so nothing the failed pass left on the model reaches the second one.
            return $this->relationsOneByOne($model->newInstance());
        }
    }

    /**
     * The relations read one method at a time, so a method that throws is left out and named in a warning.
     *
     * @return Collection<int, RelationInfo>
     */
    private function relationsOneByOne(Model $model): Collection
    {
        $relations = [];

        foreach (get_class_methods($model) as $name) {
            $method = new ReflectionMethod($model, $name);

            if (! $this->readsAsRelation($method)) {
                continue;
            }

            try {
                $relation = $method->invoke($model);

                if ($relation instanceof Relation) {
                    $relations[] = [
                        'name' => $name,
                        'type' => Str::afterLast($relation::class, '\\'),
                        'related' => $relation->getRelated()::class,
                    ];
                }
            } catch (Throwable $exception) {
                // A relation the model excludes is never published, so its failure is nothing to report.
                if ($method->getAttributes(TsExclude::class) === []) {
                    AnalysisWarnings::addOnce($model::class, sprintf(
                        'Reading its %s() relation threw [%s], so the relation is left out.',
                        $name,
                        $exception->getMessage(),
                    ));
                }
            }
        }

        return new Collection($relations);
    }

    /**
     * Whether Laravel's getRelations() would call the method: one of the model's own with no parameter, typed as a
     * relation or building one in its body.
     */
    private function readsAsRelation(ReflectionMethod $method): bool
    {
        if ($method->isStatic() || $method->isAbstract() || $method->getNumberOfParameters() > 0
            || $method->getDeclaringClass()->getName() === Model::class
        ) {
            return false;
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && is_subclass_of($returnType->getName(), Relation::class)) {
            return true;
        }

        $file = new SplFileObject((string) $method->getFileName());
        $file->seek($method->getStartLine() - 1);
        $code = '';

        while ($file->key() < $method->getEndLine()) {
            $line = $file->current();
            $code .= is_string($line) ? trim($line) : '';
            $file->next();
        }

        return array_any(
            $this->relationMethods,
            fn (string $builder): bool => str_contains($code, '$this->'.$builder.'('),
        );
    }
}
