<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource whose generic declares it holds a value object, not a model.
 *
 * @extends JsonResource<HeldStats>
 */
final class HeldStatsExtendsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource->total,
            'label' => $this->resource->label,
            'ratio' => $this->resource->ratio,
            'summary' => $this->resource->summary(),
            'proxy_total' => $this->total,
            'proxy_summary' => $this->summary(),
            'whole' => $this->resource,
        ];
    }
}
