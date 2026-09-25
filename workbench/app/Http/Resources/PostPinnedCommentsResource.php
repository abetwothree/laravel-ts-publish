<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A relation loaded under a name the model does not declare, so only the local's inline `@var` names the collection
 * it holds.
 *
 * @mixin Post
 */
final class PostPinnedCommentsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Comment> $pinned */
        $pinned = $this->resource->getRelation('pinnedComments');

        return [
            'id' => $this->id,
            'pinned_count' => $pinned->count(),
            'pinned' => $pinned,
        ];
    }
}
