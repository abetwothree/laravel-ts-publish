<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose toArray() maps comments through a closure returning an array before its own
 * `return array_merge(parent::toArray($request), …)`.
 *
 * @mixin Post
 */
class ClosureReturnBeforeMergeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $comments = $this->comments->map(function ($comment) {
            return ['comment_id' => $comment->id, 'body' => $comment->content];
        });

        return array_merge(parent::toArray($request), ['comments' => $comments]);
    }
}
