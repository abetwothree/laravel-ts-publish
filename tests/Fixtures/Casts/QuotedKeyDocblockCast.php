<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose vague get() declares a shape with a quoted key in its `@return`.
 *
 * @implements CastsAttributes<array{'a': int, b: string}, mixed>
 */
final class QuotedKeyDocblockCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{'a': int, b: string}
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ['a' => 1, 'b' => 'x'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
