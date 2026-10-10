<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource over a model with two enums named `Status`. A key under `#[TsCasts]` holds an enum resource of
 * the first one, beside a key that reads that enum bare and a key that wraps the second `Status`.
 *
 * @mixin Warehouse
 */
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastReadBesideSharedNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'a' => $this->status,
            'c' => EnumResource::make($this->current_crm_status),
        ];
    }
}
