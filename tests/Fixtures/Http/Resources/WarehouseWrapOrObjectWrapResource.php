<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource wrapping the app's `Status` in one arm and the CRM's inside an object in the other, through a
 * ternary and `when()`: each wrap names its own const (GH-108).
 *
 * @mixin Warehouse
 */
final class WarehouseWrapOrObjectWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $flag = $request->boolean('flag');

        return [
            'ternary' => $flag
                ? EnumResource::make($this->status)
                : ['inner' => EnumResource::make($this->current_crm_status)],
            'when' => $this->when(
                $flag,
                EnumResource::make($this->status),
                ['inner' => EnumResource::make($this->current_crm_status)],
            ),
        ];
    }
}
