<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource over a model with two enums named `Status`. Its one cast writes the first enum's const by the
 * bare name that both enums share.
 *
 * @mixin Warehouse
 */
#[TsCasts(['k' => 'AsEnum<typeof Status> | null'])]
class EnumResourceCastWritesSharedWrapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'a' => EnumResource::make($this->current_crm_status),
        ];
    }
}
