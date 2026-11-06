<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CommentRack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Picks at random inside a callback over a forward's comments, which no reading reaches without the guard, handing the
 * comment on in each form a value takes, or reading what is not the comment at all.
 *
 * @extends JsonResource<CommentRack>
 */
class UnreadCallbackRelaysResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $post = PageItemResource::make($this->resource->post());

        return [
            'dim_fetch' => $post->comments->map(fn ($comment) => $this->postedContent([$comment][0])),
            'ternary' => $post->comments->map(fn ($comment) => $this->postedContent($comment->id > 0 ? $comment : $comment)),
            'coalesce' => $post->comments->map(fn ($comment) => $this->postedContent($comment ?? $comment)),
            'assigned' => $post->comments->map(fn ($comment) => $this->postedContent($held = $comment)),
            'relayed' => $post->comments->map(fn ($comment) => $this->postedContent($this->same($comment))),
            'collected' => $post->comments->map(fn ($comment) => $this->postedContent(collect([$comment])->first())),
            'looped' => $post->comments->map(function ($comment) {
                $kept = $comment;

                foreach ([$comment] as $each) {
                    $kept = $each;
                }

                return $this->postedContent($kept);
            }),
            'apart' => $post->comments->map(fn ($comment) => $this->resource->comments()->pluck('content')->random()),
            'member_first' => $post->comments->map(fn ($comment) => $this->firstContent($comment->post)),
        ];
    }

    /**
     * One content picked off the comments of a comment's post.
     */
    private function postedContent(Comment $comment)
    {
        return $comment->post->comments->pluck('content')->random();
    }

    /**
     * One content picked off the comments of a post's first comment's post, or of none.
     */
    private function firstContent(Post $post)
    {
        return $post->comments->first()->post?->comments->pluck('content')->random();
    }

    /**
     * The value it is handed.
     */
    private function same($value)
    {
        return $value;
    }
}
