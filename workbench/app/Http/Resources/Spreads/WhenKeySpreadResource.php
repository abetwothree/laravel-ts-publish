<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * A `when()` key spread over an earlier `by`, which InertiaPropsWalkController resolves (GH-161 A9, C3).
 *
 * @property-read CreditLine $resource
 */
class WhenKeySpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $late = ['by' => $this->when($this->resource->flag, $this->resource->title)];

        return ['id' => 0, 'by' => $this->resource->author, ...$late];
    }
}
