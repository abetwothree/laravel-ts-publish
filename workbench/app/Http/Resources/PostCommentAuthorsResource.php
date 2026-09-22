<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A map closure over a relation held in an untyped local, whose parameter names its model. A one-step read and a
 * nullsafe chain from that parameter must both resolve against it, with and without an Elvis default.
 *
 * @mixin Post
 */
final class PostCommentAuthorsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rows = $this->resource->getRelation('comments');

        return [
            'id' => $this->id,
            'authors' => $rows->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'who' => $comment->user?->name,
                'who_or' => $comment->user?->name ?: null,
            ])->values()->all(),
        ];
    }
}
