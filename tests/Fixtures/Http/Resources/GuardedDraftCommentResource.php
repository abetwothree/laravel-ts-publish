<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DraftPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Sends a comment's loaded post whole, past an early-exit `instanceof DraftPost` guard and under the same ternary
 * test: the `post` relation's model is Post, of which DraftPost is a subclass.
 *
 * @mixin Comment
 */
class GuardedDraftCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'guarded_draft' => $this->whenLoaded('post', function ($post) {
                if (! $post instanceof DraftPost) {
                    return null;
                }

                return ['p' => $post];
            }),
            'narrowed_draft' => $this->whenLoaded('post', fn ($post) => $post instanceof DraftPost ? $post : null),
        ];
    }
}
