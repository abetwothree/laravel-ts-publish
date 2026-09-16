<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * ProxyFilterDirectResource with every only() and except() spelled through $this->resource. The resource
 * forwards to the same model either way, so the two must publish the same shape.
 *
 * @mixin Post
 */
final class ProxyFilterWrappedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->only(['id', 'title']),
            'summary' => $this->resource->only(['id', 'title']),
            'without_body' => $this->resource->except(['content', 'metadata', 'options']),
            'author_brief' => $this->resource->author->only(['id', 'name']),
            'author_rest' => $this->resource->author->except(['email']),
            'author_maybe' => $this->resource->author?->only(['id', 'name']),
            'fields_own' => $this->resource->only($request->input('fields')),
            'fields_author' => $this->resource->author->only($request->input('fields')),
        ];
    }
}
