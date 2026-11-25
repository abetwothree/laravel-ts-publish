<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * A ternary of two literals `toArray()` returns whole, which publishes neither literal's keys (GH-161 B2a).
 *
 * @property-read CreditLine $resource
 */
class ReturnedTernaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->flag ? ['by' => $this->resource->author] : ['id' => 1];
    }
}
