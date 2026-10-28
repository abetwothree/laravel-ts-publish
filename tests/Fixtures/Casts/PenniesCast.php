<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Pennies;

/**
 * A cast whose `get()` returns a class that inherits its parent's override.
 *
 * @implements CastsAttributes<Pennies, int>
 */
final class PenniesCast implements CastsAttributes
{
    /**
     * The stored integer as pennies.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Pennies
    {
        return new Pennies(is_numeric($value) ? (int) $value : 0);
    }

    /**
     * The pennies, or a number given as one, as the stored integer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return $value instanceof Pennies ? $value->value : (is_numeric($value) ? (int) $value : 0);
    }
}
