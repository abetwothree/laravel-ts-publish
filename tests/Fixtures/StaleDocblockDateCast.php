<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use DateTimeImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose precise `int` get() outranks a stale `@return` naming a date.
 *
 * @implements CastsAttributes<int, mixed>
 */
final class StaleDocblockDateCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return DateTimeImmutable
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): int
    {
        return 0;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
