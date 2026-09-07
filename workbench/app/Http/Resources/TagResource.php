<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * Exercises: whenCounted on two polymorphic relations.
 *
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * Non-toArray array-returning method fixture for the generic-method analyzer entry.
     *
     * @return array<string, mixed>
     */
    public function summaryPayload(): array
    {
        return [
            'id' => $this->id,
            'label' => (string) $this->name,
        ];
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
            'color' => $this->color,
            'posts_count' => $this->whenCounted('posts'),
            'products_count' => $this->whenCounted('products'),
            'posts' => $this->whenLoaded('posts', PostResource::collection(...)),
            'products' => $this->whenLoaded('products', ProductResource::collection(...)),
        ];
    }
}
