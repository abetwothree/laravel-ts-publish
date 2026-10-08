<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use DateTimeImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose `mixed` get() returns a date only its `@return` names, which Model::toArray() writes through
 * serializeDate().
 *
 * @implements CastsAttributes<DateTimeImmutable, mixed>
 */
final class DocblockDateCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return DateTimeImmutable
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return new DateTimeImmutable;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
