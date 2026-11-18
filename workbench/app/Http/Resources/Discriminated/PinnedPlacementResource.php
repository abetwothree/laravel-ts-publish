<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The placement a pinned post sends: one arm of PinnedPostResource's discriminated union (GH-157).
 *
 * @mixin Post
 */
class PinnedPlacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->priority,
            'pinned_at' => $this->updated_at,
        ];
    }
}
