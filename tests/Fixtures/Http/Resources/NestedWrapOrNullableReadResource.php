<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * A test-only resource whose nested key wraps a list of enums in one arm and reads it bare or sends null in the others.
 *
 * @mixin Team
 */
class NestedWrapOrNullableReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'audit' => [
                'status' => $request->boolean('wrap')
                    ? EnumResource::collection($this->status_history)
                    : ($request->boolean('read') ? $this->status_history : null),
            ],
        ];
    }
}
