<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Spreads\ArmShelf;

/**
 * An arm ArmBesideMapResource spreads beside two maps, whose `id` and `by` both fit their values (GH-161 D3).
 *
 * @property-read ArmShelf $resource
 */
class IdTitleArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => 7, 'by' => $this->resource->title];
    }
}
