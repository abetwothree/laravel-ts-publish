<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
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
         *  relations: Collection<int, RelationInfo>,
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
     * Whether a candidate method's native return type, else its docblock's, names only relation classes.
     */
    public function declaresRelationReturn(ReflectionMethod $method): bool
    {
        $classes = $this->isRelationCandidate($method)
            ? resolve(ReceiverClassResolver::class)->returnClasses($method->class, $method->name)
            : null;

        return $classes !== null && array_all(
            $classes,
            fn (string $class): bool => is_subclass_of($class, Relation::class),
        );
    }

    /**
     * Laravel's relations and those only a declared return names, each read by calling its method once, so a method
     * that throws on a blank model is left out and named in a warning.
     *
     * @param  Model  $model
     * @return Collection<int, RelationInfo>
     */
    #[Override]
    protected function getRelations($model): Collection
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
                    $relations[] = $this->relationInfo($name, $relation);
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
     * The relation's entry in the list: its method's name, Laravel's short type, the related model and its class.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return RelationInfo
     */
    private function relationInfo(string $name, Relation $relation): array
    {
        return [
            'name' => $name,
            'type' => Str::afterLast($relation::class, '\\'),
            'related' => $relation->getRelated()::class,
            'class' => $relation::class,
        ];
    }

    /**
     * Whether the method is read as a relation: one Laravel reads, typed as a relation or building one in its body, or
     * one whose docblock alone names its relation class.
     */
    private function readsAsRelation(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        // Laravel's native test goes first: most candidates are typed relations, which then skip the reflection below.
        return $this->isRelationCandidate($method) && (
            ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Relation::class))
            || $this->buildsRelationInBody($method)
            || $this->declaresRelationReturn($method)
        );
    }

    /**
     * Whether getRelations() may call the method: public, non-static, non-abstract, parameterless and not Model's own.
     */
    private function isRelationCandidate(ReflectionMethod $method): bool
    {
        return $method->isPublic() && ! $method->isStatic() && ! $method->isAbstract()
            && $method->getNumberOfParameters() === 0 && $method->class !== Model::class;
    }

    /**
     * Whether the method's body calls one of Laravel's relation builders on `$this`, as Laravel's own scan reads it.
     */
    private function buildsRelationInBody(ReflectionMethod $method): bool
    {
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
