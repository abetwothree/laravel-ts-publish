<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource whose `__get()` hands back the held object's member by reference, so a by-reference parameter binds it.
 */
final class ReferenceGetResource extends JsonResource
{
    /**
     * The held object's member, by reference.
     */
    public function &__get($key): mixed
    {
        return $this->resource->{$key};
    }
}
