<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A plain API resource nesting JSON:API resources as values. Laravel sends a single one as its resolve(), its
 * resource object under `data`, the items of its collection() bare, and each item of a named collection under `data`.
 *
 * @mixin Post
 */
class NestingPlainResource extends JsonResource
{
    /**
     * The post with its author, category, comments and the author's other posts as JSON:API resources.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => new UserResource($this->author),
            'category' => $this->categoryRel?->toResource(CategoryResource::class),
            'comments' => CommentResource::collection($this->comments),
            'siblings' => new PostCollection(Post::query()->where('user_id', $this->user_id)->get()),
        ];
    }
}
