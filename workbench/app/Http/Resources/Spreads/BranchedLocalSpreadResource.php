<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Two returns; the first spreads a local an inner `if` may write over an earlier `by` (GH-161). Where the local holds
 * `by`, Laravel sends the title there.
 *
 * @property-read CreditLine $resource
 */
class BranchedLocalSpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource->flag) {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            return ['by' => $this->resource->author, ...$maybe];
        }

        return ['n' => 1];
    }
}
