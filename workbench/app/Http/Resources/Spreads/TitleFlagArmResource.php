<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Spreads\ArmShelf;

/**
 * An arm ArmBesideMapResource spreads beside a string map, whose `by` the map's value does not fit (GH-161 D3).
 *
 * @property-read ArmShelf $resource
 */
class TitleFlagArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['name' => $this->resource->title, 'by' => $this->resource->flag];
    }
}
