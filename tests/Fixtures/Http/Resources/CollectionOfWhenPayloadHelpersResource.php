<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * A test-only resource whose helpers return an `EnumResource::collection()` of a `when*()` payload, which Laravel drops
 * with its key where the payload is missing (GH-108).
 *
 * @mixin Team
 */
final class CollectionOfWhenPayloadHelpersResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'week_days' => $this->weekDays(),
            'active_days' => $this->activeDays(),
        ];
    }

    /**
     * The team's week days, when the attribute is loaded.
     */
    private function weekDays()
    {
        return EnumResource::collection($this->whenHas('week_days'));
    }

    /**
     * The team's week days, when it is active.
     */
    private function activeDays()
    {
        return EnumResource::collection($this->when($this->is_active, $this->week_days));
    }
}
