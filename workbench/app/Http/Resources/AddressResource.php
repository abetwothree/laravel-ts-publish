<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesMorphValue;
use Workbench\App\Models\Address;

/**
 * Exercises: multiple whenNotNull on different nullable fields,
 * TsResource with explicit name/description, TsResourceCasts with import.
 *
 * @mixin Address
 */
#[TsResource(name: 'Address', description: 'Mailing address resource')]
#[TsResourceCasts([
    'coordinates' => ['type' => 'GeoPoint', 'import' => '@/types/geo'],
    'bounds' => ['type' => 'GeoBounds', 'import' => '@/types/geo'],
])]
class AddressResource extends JsonResource
{
    use IncludesMorphValue;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->includeMorphValue(),
            'id' => $this->id,
            'label' => $this->label,
            'line_1' => $this->line_1,
            'line_2' => $this->whenNotNull($this->line_2),
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'latitude' => $this->whenNotNull($this->latitude),
            'longitude' => $this->whenNotNull($this->longitude),
            'is_default' => $this->is_default,
            'user' => $this->user->only(['id', 'name']), // relation with only specific attributes
        ];
    }
}
