<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\MixedBag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads a held value object's getters, whose bodies the engine types only past a value it cannot type.
 *
 * @extends JsonResource<MixedBag>
 */
final class MixedBagResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'proxied' => $this->pick(),
            'held' => $this->resource->pick(),
            'held_mixed' => $this->resource->pickMixed(),
            'literal_null' => $this->resource->pickOrNull(true),
        ];
    }
}
