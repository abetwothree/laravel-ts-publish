<?php

declare(strict_types=1);

namespace Workbench\App\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A time of day: `get()` returns null only for a null column, and `serialize()` sends the stored string (GH-154).
 *
 * @implements CastsAttributes<?CarbonImmutable, ?CarbonImmutable>
 */
class WallClockTime implements CastsAttributes, SerializesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof CarbonImmutable ? $value->format('H:i:s') : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) $attributes[$key];
    }
}
