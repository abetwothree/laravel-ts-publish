<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose inline array spreads a helper whose #[TsCasts] displaces the enum its key reads, beside a
 * key that reads another enum of that type name.
 *
 * @mixin Warehouse
 */
class InlineCarriedEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [...$this->statuses(), 'crm' => $this->current_crm_status],
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['status' => 'string'])]
    protected function statuses(): array
    {
        return ['status' => $this->status];
    }
}
