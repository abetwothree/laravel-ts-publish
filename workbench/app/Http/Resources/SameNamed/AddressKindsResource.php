<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\SameNamed;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;

/**
 * The `Address` model beside `AddressResource`, which publishes as `Address` too (GH-76): side by side in an object, and
 * as the two arms of a ternary. Each value keeps its own class's alias.
 *
 * @mixin User
 */
class AddressKindsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'address' => new AddressResource($this->primaryAddress),
                'raw' => $this->primaryAddress,
            ],
            'either' => $request->boolean('raw') ? $this->primaryAddress : new AddressResource($this->primaryAddress),
        ];
    }
}
