<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\HeldStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A parent resource that redeclares `$resource` as a value object, with a body its subclasses inherit. */
class HeldStatsVarParentResource extends JsonResource
{
    /** @var HeldStats */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'key' => $this->getKey(),
            'res_key' => $this->resource->getKey(),
            'res_total' => $this->resource->total,
            'summary' => $this->summary(),
            'whole' => $this->resource,
        ];
    }
}
