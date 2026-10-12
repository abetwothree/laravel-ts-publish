<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;
use Workbench\App\Services\ChannelDefaults;

/**
 * Numeric keys, which a resource's filter re-indexes into a list wherever every key of an array passes is_numeric(),
 * and keeps beside a named key.
 *
 * @mixin Depot
 */
class DepotTierResource extends JsonResource
{
    public const int SURCHARGE_TIER = 3;

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // An int key and a numeric-string key are one PHP key; both re-index.
            'tiers' => [1 => 'Basic', '2' => 'Pro'],
            // '06' and '1.5' stay string keys, yet is_numeric() accepts them.
            'codes' => ['06' => 'north', '1.5' => 'south'],
            'surcharges' => [self::SURCHARGE_TIER => 2.5],
            'statuses' => ChannelDefaults::ALL_INT_KEYS,
            // A named key keeps every key, the int one written as JSON writes it.
            'labelled' => ['name' => $this->name, 42 => true],
            'nested' => ['inner' => [7 => $this->name]],
            'loaded_orders' => $this->whenLoaded('orders', fn () => [10 => 'ten', 20 => 'twenty']),
            42 => $this->name,
        ];
    }
}
