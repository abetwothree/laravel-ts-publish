<?php

namespace Workbench\Blog\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Blog\Models\Article;

/**
 * Exercises: multiple EnumResource::make, when(cond, Resource::collection),
 * whenLoaded bare (cross-module App\User as author), whenNotNull, whenCounted,
 * whenAggregated, when conditional with direct property.
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->whenNotNull($this->excerpt),
            'body' => $this->body,
            'status' => EnumResource::make($this->status),
            'content_type' => EnumResource::make($this->content_type),
            'is_featured' => $this->is_featured,
            'featured_image' => $this->when($this->is_featured, $this->featured_image),
            'meta_description' => $this->whenNotNull($this->meta_description),
            'published_at' => $this->whenNotNull($this->published_at),
            'author' => $this->whenLoaded('author'),
            'reactions' => $this->when(
                $this->relationLoaded('reactions'),
                ReactionResource::collection($this->reactions ?? collect()),
            ),
            'reactions_count' => $this->whenCounted('reactions'),
            'reactions_avg' => $this->whenAggregated('reactions', 'id', 'count'),
        ];
    }
}
