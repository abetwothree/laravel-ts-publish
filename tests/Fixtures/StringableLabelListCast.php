<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\StringableLabel;

/**
 * A cast whose `@return` lists a `__toString()` value object with only a private property, which json_encode()
 * writes as `{}`.
 *
 * @implements CastsAttributes<list<StringableLabel>, mixed>
 */
final class StringableLabelListCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<StringableLabel>
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
