<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\WrappedFlag;

/**
 * A cast with no override of its own, whose get() returns a value class typed `boolean` by its override.
 *
 * @implements CastsAttributes<WrappedFlag, bool>
 */
final class WrappedFlagValueCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): WrappedFlag
    {
        return new WrappedFlag((bool) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return $value instanceof WrappedFlag ? $value->on : (bool) $value;
    }
}
