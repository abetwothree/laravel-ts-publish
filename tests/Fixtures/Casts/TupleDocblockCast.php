<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose vague get() declares a list shape in its `@return`.
 *
 * @implements CastsAttributes<list{int, string}, mixed>
 */
final class TupleDocblockCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list{int, string}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [1, 'a'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
