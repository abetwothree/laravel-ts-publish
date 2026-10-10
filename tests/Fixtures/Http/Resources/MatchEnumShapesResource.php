<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * A test-only resource whose match mixes an EnumResource wrap with a direct read of one enum, in the two shapes
 * EnumCollectionResource gives its ternaries.
 *
 * @mixin Team
 */
class MatchEnumShapesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'history_or_scalar' => match (true) {
                $this->is_active => EnumResource::collection($this->status_history),
                default => $this->latest_status,
            },
            'history_or_array' => match (true) {
                $this->is_active => EnumResource::collection($this->status_history),
                default => $this->status_history,
            },
        ];
    }
}
