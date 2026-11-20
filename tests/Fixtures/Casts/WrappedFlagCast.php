<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Attributes\TsType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\WrappedFlag;

/**
 * A cast typed `boolean` by its own override, whose get() returns an object.
 *
 * @implements CastsAttributes<WrappedFlag, bool>
 */
#[TsType('boolean')]
final class WrappedFlagCast implements CastsAttributes
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
