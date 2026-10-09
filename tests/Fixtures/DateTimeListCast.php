<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use DateTime;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose get() lists dates: serializeDate() reaches no date inside a list, so json_encode() writes each one as
 * PHP's own object.
 *
 * @implements CastsAttributes<list<DateTime>, mixed>
 */
final class DateTimeListCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<DateTime>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [new DateTime];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
