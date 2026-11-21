<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose #[TsCasts] spells one `StatusType` over a ternary wrapping the app's and the CRM's
 * `Status`: the cast's token names the enum the key queues first (GH-108).
 *
 * @mixin Warehouse
 */
#[TsCasts(['crm_first' => 'StatusType | null', 'app_first' => 'StatusType | null'])]
class CastOneOfSameNamedWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'crm_first' => $request->boolean('crm')
                ? EnumResource::make($this->current_crm_status)
                : EnumResource::make($this->status),
            'app_first' => $request->boolean('crm')
                ? EnumResource::make($this->status)
                : EnumResource::make($this->current_crm_status),
        ];
    }
}
