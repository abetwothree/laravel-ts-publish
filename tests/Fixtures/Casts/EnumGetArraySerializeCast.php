<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/**
 * A caster without SerializesCastableAttributes whose get() returns an enum and whose serialize() writes an array.
 *
 * @implements CastsAttributes<Status, mixed>
 */
final class EnumGetArraySerializeCast implements CastsAttributes
{
    /**
     * The stored value as get() reads it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Status
    {
        return Status::from(is_numeric($value) ? (int) $value : 0);
    }

    /**
     * The value as stored.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    /**
     * The value wrapped in an array, as Model::toArray() writes it.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['v' => $value instanceof Status ? $value->value : null];
    }
}
