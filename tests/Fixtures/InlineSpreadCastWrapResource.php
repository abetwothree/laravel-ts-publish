<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose inline array spreads a helper whose #[TsCasts] spells the type of the enum its key wraps.
 *
 * @mixin Warehouse
 */
class InlineSpreadCastWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [...$this->statuses()],
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['status' => 'StatusType | null'])]
    protected function statuses(): array
    {
        return ['status' => EnumResource::make($this->status)];
    }
}
