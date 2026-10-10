<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose #[TsCasts] drops an enum resource beside a read of another enum of its type name.
 *
 * @mixin Warehouse
 */
#[TsCasts(['k' => 'string'])]
class CastDroppedEnumBesideSharedNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'crm' => $this->current_crm_status,
        ];
    }
}
