<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Reads `$this->resource` inside whenLoaded closures bound to a different relation's model, so each
 * chain must root at the resource's own model rather than at the closure's relation model.
 *
 * @mixin Post
 */
final class ClosureResourceRootResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'published_outside' => $this->resource->published_at,
            'published_inside' => $this->whenLoaded('comments', fn () => $this->resource->published_at),
            'title_inside' => $this->whenLoaded('author', fn () => $this->resource->title),
            'class_inside' => $this->whenLoaded('author', fn () => $this->resource?->getMorphClass()),
            'author_name_outside' => $this->resource->author->name,
            'author_name_inside' => $this->whenLoaded('comments', fn () => $this->resource->author->name),
            'author_name_nullsafe_inside' => $this->whenLoaded('comments', fn () => $this->resource->author?->name),
            'author_titled_outside' => $this->resource->author?->nameTitled(),
            'author_titled_inside' => $this->whenLoaded('comments', fn () => $this->resource->author?->nameTitled()),
        ];
    }
}
