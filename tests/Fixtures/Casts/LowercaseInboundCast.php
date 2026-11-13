<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;

/** An inbound-only caster, so Laravel reads its column back as stored, a NULL as null. */
final class LowercaseInboundCast implements CastsInboundAttributes
{
    /**
     * Lowercases the value it stores.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return strtolower((string) $value);
    }
}
