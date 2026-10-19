<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A body-less JSON:API resource: its attributes and relationships named by property.
 *
 * @mixin Post
 */
class PostResource extends JsonApiResource
{
    public $attributes = ['title', 'content', 'word_count', 'published_at'];

    public $relationships = [
        'author' => UserResource::class,
        'comments' => CommentResource::class,
        'categoryRel' => CategoryResource::class,
    ];
}
