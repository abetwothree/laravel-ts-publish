<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\CommentRack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads a rack's comments declared under string keys, directly and through a resource built around the rack.
 *
 * @extends JsonResource<CommentRack>
 */
class KeyedRackReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rack = CommentRackResource::make($this->resource);

        return [
            'ids' => $this->resource->keyedComments()->map(fn ($comment) => $comment->id),
            'ids_all' => $this->resource->keyedComments()->map(fn ($comment) => $comment->id)->all(),
            'all' => $this->resource->keyedComments()->all(),
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
        ];
    }
}
