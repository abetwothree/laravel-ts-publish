<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose inline array spreads a helper whose #[TsCasts] spells the enum its key reads, before a key
 * that reads another enum of that type name.
 *
 * @mixin Warehouse
 */
class InlineSpreadCastEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [...$this->statuses(), 'crm' => $this->current_crm_status],
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['status' => 'StatusType | null'])]
    protected function statuses(): array
    {
        return ['status' => $this->status];
    }
}
