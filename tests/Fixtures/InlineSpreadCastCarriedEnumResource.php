<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose inline array spreads a helper whose #[TsCasts] displaces the enum one key reads, and
 * spells that enum's type, without an import, on a key with no class behind it.
 *
 * @mixin Warehouse
 */
class InlineSpreadCastCarriedEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [...$this->statuses()],
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['status' => 'string', 'label' => 'StatusType[]'])]
    protected function statuses(): array
    {
        return ['status' => $this->status, 'label' => 'x'];
    }
}
