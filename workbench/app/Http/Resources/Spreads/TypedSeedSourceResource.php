<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Spreads\SeedShelf;

/**
 * A map method a subclass may override, spread over earlier keys, and other objects' seeded maps (GH-161 C2, Q-A).
 *
 * @property-read SeedShelf $resource
 */
class TypedSeedSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $given = $this->resource->sheet->tones();
        $given['by'] = $this->resource->title;

        $held = $this->resource->sheet->tones();

        return [
            'overridable_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$given],
            'overridable_map_local' => ['by' => $this->resource->author, 'z' => 1, ...$held],
            'overridable_map_spread' => [
                'by' => $this->resource->author,
                'z' => 1,
                ...$this->resource->sheet->tones(),
            ],
            'seed_from_other_call' => $this->resource->fromOtherCall(),
            'seed_from_property' => $this->resource->fromProperty(),
            'seed_from_coalesced' => $this->resource->fromCoalesced(),
            'seed_from_static_call' => $this->resource->fromStaticCall(),
            'seed_from_other_call_nested' => $this->resource->nestedOther(),
        ];
    }
}
