<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toAttributes() mixes attribute names with a closure Laravel calls, and whose
 * toRelationships() resolves each relationship through a closure.
 *
 * @mixin Post
 */
class ClosureRelationsPostResource extends JsonApiResource
{
    /**
     * The attributes the resource object sends: each name reads the model attribute.
     *
     * @return array<int|string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'title',
            'published_at',
            'excerpt',
            'is_published' => fn () => $this->published_at !== null,
        ];
    }

    /**
     * The relationships an include can send, each resolved by a closure.
     *
     * @return array<string, mixed>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'author' => fn () => new UserResource($this->author),
            'comments' => fn () => CommentResource::collection($this->comments->where('id', '>', 1)),
        ];
    }
}
