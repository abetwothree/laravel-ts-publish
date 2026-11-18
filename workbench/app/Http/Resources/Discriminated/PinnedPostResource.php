<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * GH-157's own report: one key's resource follows a boolean the resource also sends, so `is_pinned` should narrow
 * `placement`.
 *
 * @mixin Post
 */
class PinnedPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
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
