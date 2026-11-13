<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CommentRack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Picks at random inside callbacks and helpers handed a rack's comments, read through a forward, a branch, or the
 * rack's own declared list.
 *
 * @extends JsonResource<CommentRack>
 */
class GuardedCallbacksResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rack = CommentRackResource::make($this->resource);
        $post = PageItemResource::make($this->resource->post());
        $listed = $post->comments->all();
        $held = $post->comments;
        $branched = $rack->comments();

        if ($request->boolean('again')) {
            $branched = $rack->comments();
        }

        return [
            'forwarded' => $post->comments->map(fn ($comment) => $comment->post->comments->pluck('content')->random()),
            'built' => $rack->comments()->map(fn ($comment) => $comment->post->comments->pluck('content')->random()),
            'built_typed' => $rack->comments()->map(fn (Comment $comment) => $comment->post->comments->pluck('content')->random()),
            'own' => $this->resource->comments()->map(fn ($comment) => $comment->post->comments->pluck('content')->random()),
            'own_ids' => $this->resource->comments()->map(fn ($comment) => $comment->id),
            'own_typed_ids' => $this->resource->comments()->map(fn (Comment $comment) => $comment->post->comments->pluck('id')->random()),
            'own_first' => $this->resource->comments()->first()->post->comments->pluck('content')->random(),
            'own_sorted' => $this->resource->comments()->sortBy('id')->map(fn ($comment) => $comment->post->comments->pluck('content')->random()),
            'own_id' => $this->resource->comments()->pluck('id')->random(),
            'collected' => collect($post->comments)->map(fn ($comment) => $comment->post->comments->pluck('content')->random()),
            'array_mapped' => array_map(fn ($comment) => $comment->post->comments->pluck('content')->random(), $listed),
            'helper' => $this->pickedContent($post->comments),
            'helper_first' => $this->firstPickedContent($this->resource->comments()),
            'helper_own_id' => $this->pickedId($this->resource->comments()),
            'branched' => $branched->pluck('content')->random(),
            'held_typed_ids' => $held->map(fn (Comment $comment) => $comment->post->comments->pluck('id')->random()),
            'helper_copy_id' => $this->copiedId($post->comments->first()->replicate()),
            'collected_listed' => array_map(fn ($comment) => $comment->post->comments->pluck('content')->random(), collect($post->comments)->values()->all()),
            'collected_twice' => collect(collect($post->comments)->all())->map(fn ($comment) => $comment->post->comments->pluck('content')->random()),
            'collected_ids' => array_map(fn ($comment) => $comment->id, collect($post->comments)->all()),
            'paged' => $post->comments()->paginate()->through(fn ($comment) => $comment->id),
            'simple_paged' => $post->comments()->simplePaginate()->through(fn ($comment) => $comment->id),
        ];
    }

    /**
     * One content picked off the comments handed in.
     */
    private function pickedContent($comments)
    {
        return $comments->pluck('content')->random();
    }

    /**
     * One content picked off the first comment's post.
     */
    private function firstPickedContent($comments)
    {
        return $comments->first()->post->comments->pluck('content')->random();
    }

    /**
     * One id picked off the comments of a comment's post.
     */
    private function copiedId(Comment $comment)
    {
        return $comment->post->comments->pluck('id')->random();
    }

    /**
     * One id picked off the comments handed in.
     */
    private function pickedId($comments)
    {
        return $comments->pluck('id')->random();
    }
}
