<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource over a model with two enums named `Status`. A key under `#[TsCasts]` holds a ternary over the
 * first one's resource and another enum's, beside a key that wraps the second `Status`.
 *
 * @mixin Warehouse
 */
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastTernaryBesideSharedNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->priority),
            'a' => EnumResource::make($this->current_crm_status),
        ];
    }
}
