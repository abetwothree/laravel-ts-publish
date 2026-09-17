<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * Reads the `menu_config` accessor, typed by a `#[TsType(import:)]` class, under a key no accessor shares.
 *
 * @mixin Warehouse
 */
final class WarehouseSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settings' => $this->menu_config,
        ];
    }
}
