<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

/**
 * @template TModel of Model
 *
 * @phpstan-type ModelClassType = class-string<TModel>
 * @phpstan-type PolicyClassType = class-string|null
 * @phpstan-type AttributeInfo = array{name: string, type: string|null, cast: string|null, nullable: bool}
 * @phpstan-type RelationInfo = array{name: string, type: string, related: class-string<Model>}
 * @phpstan-type AttributesType = Collection<int, AttributeInfo>
 * @phpstan-type RelationsType = Collection<int, RelationInfo>
 * @phpstan-type EventsType = Collection<int, array{event: string, class: string}>
 * @phpstan-type ObserversType = Collection<int, array{event: string, observer: array<int, string>}>
 * @phpstan-type CollectionType = class-string<EloquentCollection<int, TModel>>
 * @phpstan-type BuilderType = class-string<Builder<TModel>>
 * @phpstan-type ResourceType = class-string<JsonResource>|null
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 *
 * @internal
 */
class ModelInfo implements Arrayable, ArrayAccess
{
    /**
     * @param  ModelClassType  $class  The model's fully-qualified class.
     * @param  string  $database  The database connection name.
     * @param  string  $table  The database table name.
     * @param  PolicyClassType  $policy  The policy that applies to the model.
     * @param  AttributesType  $attributes  The attributes available on the model.
     * @param  RelationsType  $relations  The relations defined on the model.
     * @param  EventsType  $events  The events that the model dispatches.
     * @param  ObserversType  $observers  The observers registered for the model.
     * @param  CollectionType  $collection  The Collection class that collects the models.
     * @param  BuilderType  $builder  The Builder class registered for the model.
     * @param  ResourceType  $resource  The JSON resource that represents the model.
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
     *     "class": ModelClassType,
     *     database: string,
     *     table: string,
     *     policy: PolicyClassType,
     *     attributes: AttributesType,
     *     relations: RelationsType,
     *     events: EventsType,
     *     observers: ObserversType,
     *     collection: CollectionType,
     *     builder: BuilderType,
     *     resource: ResourceType,
     * }
     */
    public function toArray(): array
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
