<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * Filters its model through `$this->resource->except()`, dropping the one accessor that names only the `#[TsType]` class.
 *
 * @mixin Stockroom
 */
final class StockroomTrimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->except(['id', 'menu_config']);
    }
}
