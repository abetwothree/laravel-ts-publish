<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Casts away the CRM `User` of `contact` and both enums of the `review_priority` accessor, so the only class left to
 * import is the app's own `User`, under its own name.
 *
 * @mixin Warehouse
 */
#[TsCasts([
    'contact' => '{ id: number; name: string } | null',
    'review_priority' => 'string',
])]
class WarehouseContactCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manager' => $this->manager,
            'contact' => $this->primaryContact,
            ...$this->only(['review_priority']),
        ];
    }
}
