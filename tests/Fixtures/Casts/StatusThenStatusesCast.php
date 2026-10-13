<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/**
 * A cast whose get() documents one case of an enum before a list of it.
 *
 * @implements CastsAttributes<Status|Status[], mixed>
 */
final class StatusThenStatusesCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return Status|Status[]
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_array($value) ? array_map(Status::from(...), $value) : Status::from((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
