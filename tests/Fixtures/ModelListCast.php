<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\User;

/**
 * A cast whose `@return` names a model, a class token the cast's type cannot import.
 *
 * @implements CastsAttributes<list<User>, mixed>
 */
final class ModelListCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<User>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
