<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/**
 * Exercises: self-referencing Resource::make and Resource::collection,
 * when conditional, whenCounted, cross-resource PostResource::collection.
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Foreign receiver for FluentSelfResource's `foreign_summary` boundary fixture. It deliberately
     * shares the `summary()` name with FluentSelfResource::summary() and returns a different shape,
     * so the analyzer resolving this call against its own reflection would emit `{ id: number }`
     * instead of this method's `{ slug: string }` — the negative test would catch that.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return ['slug' => $this->slug];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when($this->is_active, $this->description),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'parent' => self::make($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'posts_count' => $this->whenCounted('posts'),
            'children_self_collection' => self::collection($this->children),
            'children_self_resource_collection' => self::collection($this->resource->children),
            'children_self_collection_first_callable' => self::collection(...),
            'children_when_self_collection' => $this->whenLoaded('children', self::collection($this->children)),
            'children_when_self_resource_collection' => $this->whenLoaded('children', self::collection($this->resource->children)),
            'children_when_self_collection_first_callable' => $this->whenLoaded('children', self::collection(...)),
            'parent_self' => new self($this->parent),
            'parent_make_self' => self::make($this->parent),
            'parent_resource_self' => new self($this->resource->parent),
            'parent_when_self' => $this->whenLoaded('parent', fn () => new self($this->parent)),
            'parent_when_resource_self' => $this->whenLoaded('parent', fn () => new self($this->resource->parent)),
            'children_with_default' => $this->whenLoaded('children', $this->children, []),
            'posts_with_default' => $this->whenLoaded('posts', PostResource::collection($this->posts), []),
        ];
    }
}
