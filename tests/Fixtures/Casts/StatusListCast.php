<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/**
 * A cast whose get() documents a list of an enum alone.
 *
 * @implements CastsAttributes<Status[], mixed>
 */
final class StatusListCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return Status[]
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return array_map(Status::from(...), (array) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
