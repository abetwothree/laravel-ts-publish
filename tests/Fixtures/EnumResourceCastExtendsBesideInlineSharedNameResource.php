<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource over a model with two enums named `Status`. A key under `#[TsCasts]` holds an enum resource of
 * the first one, while an extends clause names its type and the second `Status` is wrapped only inside an inline array.
 *
 * @mixin Warehouse
 */
#[TsExtends('Partial<Record<StatusType, unknown>>')]
#[TsCasts(['k' => 'string | null'])]
class EnumResourceCastExtendsBesideInlineSharedNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
            'inline' => ['crm' => EnumResource::make($this->current_crm_status)],
        ];
    }
}
