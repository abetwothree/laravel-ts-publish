<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * A helper whose local may leave `by` unset, defaulted by `??=`, spread over an earlier `by` (GH-161). The helper
 * always writes `by`, so Laravel sends the title or `'z'`, never the author.
 *
 * @property-read CreditLine $resource
 */
class CoalescedHelperSpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => 0, 'by' => $this->resource->author, ...$this->coalesced()];
    }

    /**
     * A local that may hold `by`, defaulted by `??=`.
     */
    private function coalesced(): array
    {
        $data = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $data['by'] ??= 'z';

        return $data;
    }
}
