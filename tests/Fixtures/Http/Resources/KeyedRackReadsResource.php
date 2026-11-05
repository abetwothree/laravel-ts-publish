<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CommentRack;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;

/**
 * Reads a rack's comments declared under string keys, directly, through a resource built around the rack, and through
 * locals an inline `@var` declares.
 *
 * @extends JsonResource<CommentRack>
 */
class KeyedRackReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rack = CommentRackResource::make($this->resource);
        $held = $this->resource->keyedComments();
        /** @var Collection<string, Comment> $declared */
        $declared = $this->resource->keyedComments();
        /** @var EloquentCollection<string, Comment> $declaredEloquent */
        $declaredEloquent = $this->resource->keyedEloquent();

        return [
            'ids' => $this->resource->keyedComments()->map(fn ($comment) => $comment->id),
            'ids_all' => $this->resource->keyedComments()->map(fn ($comment) => $comment->id)->all(),
            'all' => $this->resource->keyedComments()->all(),
            'held_all' => $held->all(),
            'either_all' => ($request->boolean('again') ? $this->resource->keyedComments() : $held)->all(),
            'built_all' => $rack->keyedComments()->all(),
            'eloquent_all' => $this->resource->keyedEloquent()->all(),
            'any_all' => $this->resource->anyKeyed()->all(),
            'arrays' => $this->resource->keyedComments()->toArray(),
            'eloquent_arrays' => $this->resource->keyedEloquent()->toArray(),
            'model_keys' => $this->resource->keyedEloquent()->modelKeys(),
            'built_model_keys' => $rack->keyedEloquent()->modelKeys(),
            'built_ids' => $rack->keyedComments()->map(fn ($comment) => $comment->id),
            'any_ids' => $this->resource->anyKeyed()->map(fn ($comment) => $comment->id),
            'random' => $this->resource->keyedComments()->random(),
            'random_pair' => $this->resource->keyedComments()->random(2),
            'only' => $this->resource->keyedEloquent()->only([3]),
            'sorted' => $this->resource->keyedComments()->sortBy('id')->all(),
            'declared_all' => $declared->all(),
            'declared_first_id' => $declared->first()->id,
            'declared_random' => $declared->random(),
            'declared_eloquent_all' => $declaredEloquent->all(),
        ];
    }
}
