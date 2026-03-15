<?php

namespace Workbench\App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\Coordinate;

/**
 * @implements CastsAttributes<Coordinate, string>
 */
class CoordinateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Coordinate
    {
        return Coordinate::fromString((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof Coordinate ? $value->toString() : (string) $value;
    }
}
