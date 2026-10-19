<?php

declare(strict_types=1);

namespace Workbench\App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/**
 * A cast whose get() documents a list of an enum or one case, so the column publishes both arms.
 *
 * @implements CastsAttributes<Status[]|Status, mixed>
 */
class StatusOrStatusesCast implements CastsAttributes
{
    /**
     * Reads a list of cases, or one case.
     *
     * @param  array<string, mixed>  $attributes
     * @return Status[]|Status
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return is_array($value) ? array_map(Status::from(...), $value) : Status::from((int) $value);
    }

    /**
     * Stores the value as given.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
