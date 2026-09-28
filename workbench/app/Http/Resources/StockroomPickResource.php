<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * Filters its model through `$this->resource->only()`, keeping two accessors that each name a model and a `#[TsType]` class.
 *
 * @mixin Stockroom
 */
final class StockroomPickResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->only(['id', 'contact', 'layout']);
    }
}
