<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose `when()` holds an enum resource on each arm, over two enums that share a name: the
 * warehouse's own `Status` and the CRM `Status` behind `current_crm_status`.
 *
 * @mixin Warehouse
 */
class EnumResourceArmsWarehouseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'status_or_crm_status' => $this->when(
                $request->boolean('own'),
                EnumResource::make($this->status),
                EnumResource::make($this->current_crm_status),
            ),
            'crm_status_or_status' => $this->when(
                $request->boolean('crm'),
                EnumResource::make($this->current_crm_status),
                EnumResource::make($this->status),
            ),
        ];
    }
}
