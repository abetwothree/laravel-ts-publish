<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The placement an unpinned post sends: one arm of PinnedPostResource's discriminated union (GH-157).
 *
 * @mixin Post
 */
class FeedPlacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'published_at' => $this->published_at,
            'rating' => $this->rating,
        ];
    }
}
