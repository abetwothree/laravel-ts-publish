<?php

namespace AbeTwoThree\LaravelTsPublish;

use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector as EloquentModelInspector;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;

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
            $data = $modelInfo->toArray(); // @codeCoverageIgnore
            $modelInfo = $data; // @codeCoverageIgnore
        }

        /** @var array{
         *  class: class-string<Model>,
         *  database: string,
         *  table: string,
         *  policy: class-string|null,
         *  attributes: Collection<int, array{name: string, type: string|null, cast: string|null, nullable: bool}>,
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
}
