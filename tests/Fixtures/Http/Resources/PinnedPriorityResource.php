<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Priority;
use Workbench\App\Http\Resources\Discriminated\FeedPlacementResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPlacementResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource with two keys others follow, and no key every arm shares.
 *
 * @mixin Post
 */
class PinnedPriorityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_pinned' => $this->is_pinned,
            'placement' => $this->is_pinned
                ? new PinnedPlacementResource($this->resource)
                : new FeedPlacementResource($this->resource),
            'priority' => $this->priority,
            'escalated_at' => $this->when($this->priority === Priority::Critical, $this->updated_at),
        ];
    }
}
