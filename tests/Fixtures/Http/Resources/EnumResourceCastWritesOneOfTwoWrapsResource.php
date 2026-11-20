<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource over a model with two enums named `Status`. Its one cast writes one wrap where the key wraps
 * both enums.
 *
 * @mixin Warehouse
 */
#[TsCasts(['k' => '{ a: AsEnum<typeof Status> }'])]
class EnumResourceCastWritesOneOfTwoWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => ['a' => EnumResource::make($this->status), 'b' => EnumResource::make($this->current_crm_status)],
        ];
    }
}
