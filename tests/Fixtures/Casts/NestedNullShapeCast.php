<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose get() runs on a `NULL` column and never returns null, though its shape holds a nested null.
 *
 * @implements CastsAttributes<array{a: ?int}, mixed>
 */
final class NestedNullShapeCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{a: ?int}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['a' => null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
