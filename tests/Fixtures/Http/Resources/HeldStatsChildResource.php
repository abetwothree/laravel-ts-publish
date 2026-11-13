<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\HeldStatsParentResource;
use Illuminate\Http\Request;

/** A resource with no tag of its own, holding the value object its parent's generic names. */
final class HeldStatsChildResource extends HeldStatsParentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource->total,
            'proxy_total' => $this->total,
            'proxy_summary' => $this->summary(),
        ];
    }
}
