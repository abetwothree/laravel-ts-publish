<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Reads the two-enum `review_priority` accessor through a value-less whenAppended(), under a key no accessor shares.
 *
 * @mixin Warehouse
 */
final class WarehouseReviewAppendedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_level' => $this->whenAppended('review_priority'),
        ];
    }
}
