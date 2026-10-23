<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * only() and except() on a variable that holds a relation's Eloquent collection pick whole models by primary key, and
 * list them, but for a null key list, which keeps the keys a keyBy() set.
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
        ];
    }
}
