<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\DepotTierKeyedResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource collecting a resource that preserves keys, whose collection copies that flag to each element.
 *
 * @mixin Depot
 */
class KeyedTierCollectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['tiers' => DepotTierKeyedResource::collection($this->whenLoaded('tiers'))];
    }
}
