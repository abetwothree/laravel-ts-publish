<?php

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

/**
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 *
 * @internal
 */
class ModelInfo implements Arrayable, ArrayAccess
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $class  The model's fully-qualified class.
     * @param  string  $database  The database connection name.
     * @param  string  $table  The database table name.
     * @param  class-string|null  $policy  The policy that applies to the model.
     * @param  Collection<int, array<string, mixed>>  $attributes  The attributes available on the model.
     * @param  Collection<int, array{name: string, type: string, related: class-string<Model>}>  $relations  The relations defined on the model.
     * @param  Collection<int, array{event: string, class: string}>  $events  The events that the model dispatches.
     * @param  Collection<int, array{event: string, observer: array<int, string>}>  $observers  The observers registered for the model.
     * @param  class-string<\Illuminate\Database\Eloquent\Collection<int, TModel>>  $collection  The Collection class that collects the models.
     * @param  class-string<Builder<TModel>>  $builder  The Builder class registered for the model.
     * @param  class-string<JsonResource>|null  $resource  The JSON resource that represents the model.
     */
    public function __construct(
        public string $class,
        public string $database,
        public string $table,
        public ?string $policy,
        public Collection $attributes,
        public Collection $relations,
        public Collection $events,
        public Collection $observers,
        public string $collection,
        public string $builder,
        public ?string $resource,
    ) {}

    /**
     * Convert the model info to an array.
     *
     * @return array{
     *     "class": class-string<Model>,
     *     database: string,
     *     table: string,
     *     policy: class-string|null,
     *     attributes: Collection<int, array<string, mixed>>,
     *     relations: Collection<int, array{name: string, type: string, related: class-string<Model>}>,
     *     events: Collection<int, array{event: string, class: string}>,
     *     observers: Collection<int, array{event: string, observer: array<int, string>}>,
     *     collection: class-string<\Illuminate\Database\Eloquent\Collection<int, Model>>,
     *     builder: class-string<Builder<Model>>,
     *     resource: class-string<JsonResource>|null
     * }
     */
    public function toArray()
    {
        return [
            'class' => $this->class,
            'database' => $this->database,
            'table' => $this->table,
            'policy' => $this->policy,
            'attributes' => $this->attributes,
            'relations' => $this->relations,
            'events' => $this->events,
            'observers' => $this->observers,
            'collection' => $this->collection,
            'builder' => $this->builder,
            'resource' => $this->resource,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, (string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $key = (string) $offset;

        return property_exists($this, $key) ? $this->{$key} : throw new InvalidArgumentException("Property {$key} does not exist.");
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException(self::class.' may not be mutated using array access.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException(self::class.' may not be mutated using array access.');
    }
}
