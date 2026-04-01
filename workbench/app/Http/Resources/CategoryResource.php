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
            'parent' => CategoryResource::make($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
