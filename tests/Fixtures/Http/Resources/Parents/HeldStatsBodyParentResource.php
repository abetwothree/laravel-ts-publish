<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A parent resource whose generic declares a value object, with a body its subclasses inherit.
 *
 * @extends JsonResource<HeldStats>
 */
class HeldStatsBodyParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'key' => $this->getKey(),
            'res_key' => $this->resource->getKey(),
            'whole' => $this->resource,
        ];
    }
}
