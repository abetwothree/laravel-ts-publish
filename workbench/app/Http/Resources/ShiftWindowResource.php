<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\ShiftWindow;

/**
 * Reads a plain string column through `getAttribute()` under a `toArray()` `@return` docblock that names the values it
 * holds (GH-154).
 *
 * @property-read ShiftWindow $resource
 */
class ShiftWindowResource extends JsonResource
{
    /**
     * @return array{label: 'early'|'late'}
     */
    public function toArray(Request $request): array
    {
        return ['label' => $this->resource->getAttribute('label')];
    }
}
