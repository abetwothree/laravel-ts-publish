<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A parent resource whose generic declares a value object, with a body reading it whole and through the proxy.
 *
 * @extends JsonResource<HeldStats>
 */
class HeldStatsMembersParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['whole' => $this->resource, 'total' => $this->total, 'flag' => $this->flag];
    }
}
