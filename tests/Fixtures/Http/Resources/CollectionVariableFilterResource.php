<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * only() and except() on a variable that holds a relation's Eloquent collection, or on a method's declared one, pick
 * whole models by primary key, and list them, but for a null key list, which keeps the keys a keyBy() set.
 *
 * @mixin Post
 */
class CollectionVariableFilterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $comments = $this->resource->getRelation('comments');

        return [
            'only' => $this->whenLoaded('comments', fn ($comments) => $comments->only([1, 2])),
            'except' => $this->whenLoaded('comments', fn ($comments) => $comments->except([1])),
            'local_only' => $comments->only([1]),
            'relation_only' => $this->comments->only([1, 2]),
            'keyed_only' => $comments->keyBy('content')->only([1]),
            'keyed_only_null' => $comments->keyBy('content')->only(null),
            'method_only' => $this->latestComments()->only([1, 2]),
        ];
    }

    /**
     * The post's comments, which only the declaration lists.
     *
     * @return Collection<int, Comment>
     */
    private function latestComments(): Collection
    {
        return $this->resource->getRelation('comments');
    }
}
