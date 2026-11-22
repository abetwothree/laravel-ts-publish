<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * An arm BaseFormsSpreadResource spreads inside a merge, over an earlier key of its own name (GH-121 B6).
 *
 * @property-read CreditLine $resource
 */
class CreditArmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['credited_title' => $this->resource->title, 'credited_flag' => $this->resource->flag];
    }
}
