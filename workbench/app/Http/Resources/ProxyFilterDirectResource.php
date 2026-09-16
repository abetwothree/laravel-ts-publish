<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * only() and except() written against $this, in spread and value position, on the resource's own model, a
 * single-model relation and a many-relation, with literal and runtime key lists. ProxyFilterWrappedResource
 * spells every call through $this->resource and must publish exactly this shape.
 *
 * A many-relation filter keeps whole models by primary key, so it publishes the relation's own list type.
 *
 * @mixin Post
 */
final class ProxyFilterDirectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['id', 'title']),
            'summary' => $this->only(['id', 'title']),
            'without_body' => $this->except(['content', 'metadata', 'options']),
            'author_brief' => $this->author->only(['id', 'name']),
            'author_rest' => $this->author->except(['email']),
            'author_maybe' => $this->author?->only(['id', 'name']),
            'fields_own' => $this->only($request->input('fields')),
            'fields_author' => $this->author->only($request->input('fields')),
            'except_own' => $this->except($request->input('fields')),
            'except_author' => $this->author->except($request->input('fields')),
            'comments_by_key' => $this->comments->only([1, 2]),
            'comments_listed' => $this->comments->only(['id', 'content']),
            'comments_by_ids' => $this->comments->only($request->input('ids')),
            'comments_maybe' => $this->comments?->only($request->input('ids')),
        ];
    }
}
