<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use DateTime;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A class cast whose get() returns a plain DateTime, which Model::toArray() writes through serializeDate().
 *
 * @implements CastsAttributes<DateTime|null, mixed>
 */
final class DateTimeCast implements CastsAttributes
{
    /**
     * The stored value as a date.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?DateTime
    {
        return is_string($value) ? new DateTime($value) : null;
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
}
