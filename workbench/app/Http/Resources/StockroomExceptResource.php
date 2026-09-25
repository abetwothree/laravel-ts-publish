<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Stockroom;

/**
 * Filters itself through `$this->except()`, dropping the one accessor that names only the `#[TsType]` class.
 *
 * @mixin Stockroom
 */
final class StockroomExceptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->except(['id', 'menu_config']);
    }
}
