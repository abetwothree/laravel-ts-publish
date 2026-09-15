<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * Property chains read from a local variable that holds a model, with and without nullsafe steps.
 * Every expression is written twice, once through `$this->` and once through `$this->resource->`.
 *
 * @mixin Comment
 */
final class ReceiverPropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $post = $this->post;
        $resourcePost = $this->resource->post;

        return [
            'post_title' => $post?->title,
            'post_published_at' => $post?->published_at,
            'post_author_name' => $post?->author?->name,
            'post_title_direct' => $post->title,
            'resource_post_title' => $resourcePost?->title,
            'resource_post_published_at' => $resourcePost?->published_at,
            'resource_post_author_name' => $resourcePost?->author?->name,
            'resource_post_title_direct' => $resourcePost->title,
            'post_title_via_this' => $this->post?->title,
            'post_title_via_resource' => $this->resource->post?->title,
        ];
    }
}
