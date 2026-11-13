<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A caster whose `get()` declares `mixed` natively and a string only in its docblock, and passes a NULL through.
 *
 * @implements CastsAttributes<string, string>
 */
final class MixedGetCast implements CastsAttributes
{
    /**
     * Reads the stored value as it is.
     *
     * @param  array<string, mixed>  $attributes
     * @return string
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    /**
     * Stores the value as a string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) $value;
    }
}
