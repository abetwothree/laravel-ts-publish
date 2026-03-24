<?php

namespace Workbench\Blog\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Workbench\App\Http\Resources\CommonResource;
use Workbench\Blog\Models\Article;

/**
 * @mixin Article
 */
class ApiArticleResource extends CommonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            ...$this->only([
                'id',
                'title',
                'slug',
                'excerpt',
                'body',
            ]),
            'status' => EnumResource::make($this->status),
            'content_type' => new EnumResource($this->content_type),
            'is_featured' => $this->is_featured,
            'author' => $this->whenLoaded('author'),
        ];
    }
}
