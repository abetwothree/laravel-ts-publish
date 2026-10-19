<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\DepotTierResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource that preserves keys and spreads a resolved resource that does not, whose own filter has already
 * re-indexed its arrays.
 *
 * @mixin Depot
 */
class KeyedDepotTierSpreadResource extends JsonResource
{
    public $preserveKeys = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...DepotTierResource::make($this->resource)->resolve($request),
            'own' => [1 => 'kept'],
        ];
    }
}
