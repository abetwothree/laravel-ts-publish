<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Consignment;

/**
 * Reads each cast through the model, so the resource publishes what the cast returns, as the model does.
 *
 * @mixin Consignment
 */
class ConsignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scanned_at' => $this->scanned_at,
            'declared_value' => $this->declared_value,
            'legs' => $this->legs,
        ];
    }
}
