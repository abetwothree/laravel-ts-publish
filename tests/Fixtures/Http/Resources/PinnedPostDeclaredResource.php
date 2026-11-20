<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Discriminated\FeedPlacementResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPlacementResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose `@return` types the key that follows `is_pinned` as `mixed`, which leaves its row as the
 * body reads it, so its arms still describe what it sends.
 *
 * @mixin Post
 */
class PinnedPostDeclaredResource extends JsonResource
{
    /**
     * @return array{id: int, is_pinned: bool, placement: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_pinned' => $this->is_pinned,
            'placement' => $this->is_pinned
                ? new PinnedPlacementResource($this->resource)
                : new FeedPlacementResource($this->resource),
        ];
    }
}
