<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\Deal;

/**
 * A test-only resource whose one key is an inline array that wraps one enum, a second enum that shares its const name,
 * and the first enum again.
 *
 * @mixin Deal
 */
class EnumResourceWrapTrioResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'trio' => [
                'a' => EnumResource::make($this->status),
                'b' => EnumResource::make($this->crm_status),
                'c' => EnumResource::make($this->status),
            ],
        ];
    }
}
