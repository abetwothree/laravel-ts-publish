<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A whenLoaded() Post parameter reassigned to its author: the Post's member and method no longer answer for it.
 *
 * @mixin Comment
 */
class ReassignedPostParamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // A User has no `title`, so Laravel sends null.
            'old_prop' => $this->whenLoaded('post', function (Post $post) {
                $post = $post->author;

                return $post->title;
            }),
            'new_prop' => $this->whenLoaded('post', function (Post $post) {
                $post = $post->author;

                return $post->name;
            }),
            // A User has no commentsCount(), so the call throws.
            'old_method' => $this->whenLoaded('post', function (Post $post) {
                $post = $post->author;

                return $post->commentsCount();
            }),
        ];
    }
}
