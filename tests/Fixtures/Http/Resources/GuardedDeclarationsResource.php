<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CommentRack;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Enumerable;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Reads a rack's comments where a declaration types them: a callback's or a helper's declared parameter, and an
 * inline `@var` over what a forward hands back.
 *
 * @extends JsonResource<CommentRack>
 */
class GuardedDeclarationsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rack = CommentRackResource::make($this->resource);
        $post = PageItemResource::make($this->resource->post());
        $held = $post->comments;
        /** @var EloquentCollection<int, Comment> $declared */
        $declared = $rack->bare();
        $declaredAll = $declared->all();
        /** @var EloquentCollection<int, Comment> $listed */
        $listed = $post->comments;
        /** @var EloquentCollection<int, Comment> $own */
        $own = $this->resource->comments();

        return [
            'array_typed' => array_map(fn (Comment $comment) => $comment->post->comments->pluck('id')->random(), $post->comments->all()),
            'array_typed_held' => array_map(fn (Comment $comment) => $comment->post->comments->pluck('id')->random(), $held->all()),
            'array_typed_declared' => array_map(fn (Comment $comment) => $comment->post->comments->pluck('id')->random(), $declared->all()),
            'declared_array' => array_map(fn ($comment) => $comment->post->comments->pluck('id')->random(), $declared->all()),
            'declared_array_local' => array_map(fn ($comment) => $comment->post->comments->pluck('id')->random(), $declaredAll),
            'declared_collected' => collect($declared)->map(fn ($comment) => $comment->post->comments->pluck('id')->random()),
            'helper_interface' => $this->enumerablePick($post->comments),
            'helper_interface_built' => $this->enumerablePick($rack->comments()),
            'helper_interface_first' => $this->enumerableFirst($post->comments),
            'declared_forward_map' => $listed->map(fn ($comment) => $comment->post->comments->pluck('id')->random()),
            'declared_forward_array' => array_map(fn ($comment) => $comment->post->comments->pluck('id')->random(), $listed->all()),
            'declared_forward_pick' => $listed->pluck('id')->random(),
            'declared_forward_first' => $listed->first()->post->comments->pluck('id')->random(),
            'callback_helper' => $post->comments->map(fn ($comment) => $this->postedContent($comment)),
            'callback_helper_built' => $rack->comments()->map(fn ($comment) => $this->postedContent($comment)),
            'callable_helper' => $post->comments->map($this->postedContent(...)),
            'callback_helper_own' => $this->resource->comments()->map(fn ($comment) => $this->postedId($comment)),
            'direct_helper' => $this->postedId($post->comments->first()),
            'declared_own_first' => $own->first()->post->comments->pluck('id')->random(),
            'callback_relay' => $post->comments->map(fn ($comment) => $this->relayedContent($comment)),
            'callback_member_helper' => $post->comments->map(fn ($comment) => $this->postPicked($comment->post)),
            'callback_local_helper' => $post->comments->map(function ($comment) {
                $held = $comment;

                return $this->postedContent($held);
            }),
        ];
    }

    /**
     * One id picked off the comments of a comment's post.
     */
    private function postedId(Comment $comment)
    {
        return $comment->post->comments->pluck('id')->random();
    }

    /**
     * One content picked off the comments of a comment's post.
     */
    private function postedContent(Comment $comment)
    {
        return $comment->post->comments->pluck('content')->random();
    }

    /**
     * One content picked off a post's comments.
     */
    private function postPicked(Post $post)
    {
        return $post->comments->pluck('content')->random();
    }

    /**
     * What postedContent() picks, handed on by a helper that declares nothing.
     */
    private function relayedContent($comment)
    {
        return $this->postedContent($comment);
    }

    /**
     * One id picked off any collection of comments.
     */
    private function enumerablePick(Enumerable $comments)
    {
        return $comments->pluck('id')->random();
    }

    /**
     * One content picked off the first comment's post.
     */
    private function enumerableFirst(Enumerable $comments)
    {
        return $comments->first()->post->comments->pluck('content')->random();
    }
}
