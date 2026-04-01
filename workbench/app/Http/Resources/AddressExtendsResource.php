<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesMorphValue;
use Workbench\App\Models\Address;

/**
 * Exercises: reading model from @extends ParentClass<Model> in docblock
 *
 * Do not change, it needs to match the AddressMixinResource exactly
 *
 * @extends JsonResource<Address>
 */
class AddressExtendsResource extends JsonResource
{
    use IncludesMorphValue;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->includeMorphValue(),
            ...$this->only([
                'id',
                'full_address',
            ]),
            'latitude' => $this->whenNotNull($this->latitude),
            'longitude' => $this->whenNotNull($this->longitude),
            'user' => $this->whenLoaded('user'),
        ];
    }
}
