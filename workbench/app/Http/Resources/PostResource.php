<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\Concerns\IncludesMorphValue;
use Workbench\App\Models\Post;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    use IncludesMorphValue;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->includeMorphValue(),
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => EnumResource::make($this->status),
            'status_new' => new EnumResource($this->status),
            'visibility' => EnumResource::make($this->visibility),
            'visibility_new' => new EnumResource($this->visibility),
            'priority' => EnumResource::make($this->priority),
            'priority_new' => new EnumResource($this->priority),
            'comments' => $this->comments->only('id', 'content', 'user'),
            'published' => (bool) $this->published_at, // attribute with cast but no return type annotation — analyzer should resolve type from cast to boolean
            'publishable' => $this->publishable(), // method call with return type annotation
            'comments_count' => $this->resource->commentsCount(), // method call accessed via $this->resource with return type annotation
            'is_featured' => $this->isFeatured(), // method with doc block annotation only — analyzer should resolve type from doc block
            'category_is_first' => $this->whenLoaded('categoryRel', fn () => $this->categoryRel?->isFirst()), // relation method call with return type annotation
            'category_is_active' => $this->whenLoaded('categoryRel', fn () => $this->resource->categoryRel?->isActive()), // relation method call with doc block annotation only
            'category_breadcrumb' => $this->whenLoaded('categoryRel', fn () => $this->categoryRel?->breadcrumb), // relation with Attribute accessor with return type annotation
            'comments_resolved' => $this->whenLoaded('comments', CommentResource::collection($this->comments)->resolve()), // Resolve anonymous CommentResource collection to test that Type is CommentResource[]
        ];
    }
}
