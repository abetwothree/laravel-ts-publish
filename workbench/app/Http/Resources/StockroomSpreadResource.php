<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * Spreads `$this->only()` over two accessors that each name a model and a `#[TsType]` class.
 *
 * @mixin Stockroom
 */
final class StockroomSpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->only(['contact', 'layout']), 'id' => $this->id];
    }
}
