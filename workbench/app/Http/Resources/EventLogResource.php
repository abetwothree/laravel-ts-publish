<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource with non-conventional name — tests #[UseResource] attribute model resolution.
 *
 * The backing model (TrackingEvent) uses #[UseResource(EventLogResource::class)]
 * to point to this resource since it can't be found by naming convention.
 */
class EventLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
        ];
    }
}
