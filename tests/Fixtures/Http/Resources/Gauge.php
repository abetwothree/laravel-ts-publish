<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The bare naming-convention candidate for GaugeCollection: an API resource named like a model, which Laravel's
 * collects() takes before GaugeResource.
 */
class Gauge extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gauge_id' => 1,
        ];
    }
}
