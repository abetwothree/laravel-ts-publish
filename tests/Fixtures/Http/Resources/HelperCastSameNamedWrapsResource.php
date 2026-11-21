<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose helpers' `#[TsCasts]` cover wraps of the app's and the CRM's `Status`, beside reads of
 * both, under one key each (GH-160).
 *
 * @mixin Warehouse
 */
class HelperCastSameNamedWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => ['a' => $this->held(), 'b' => EnumResource::make($this->current_crm_status), 'c' => $this->status],
            'm' => ['a' => $this->mixed(), 'c' => $this->current_crm_status],
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['w' => 'StatusType', 'v' => 'StatusType | null'])]
    protected function held(): array
    {
        return [
            'w' => EnumResource::make($this->status),
            'v' => EnumResource::make($this->current_crm_status),
            'x' => $this->current_crm_status,
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['w' => 'string'])]
    protected function mixed(): array
    {
        return ['w' => EnumResource::make($this->status), 'y' => EnumResource::make($this->current_crm_status)];
    }
}
