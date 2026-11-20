<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Discriminated\FeedPlacementResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPlacementResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose `toArray()` `#[TsCasts]` types the key that follows `is_pinned`, so its keys publish flat.
 *
 * @mixin Post
 */
class PinnedPostMethodCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['placement' => 'PinnedPlacementResource | FeedPlacementResource'])]
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
