<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Comment;

/**
 * A JSON:API resource with a to-one relationship of its own, which a nested include follows.
 *
 * @mixin Comment
 */
class CommentResource extends JsonApiResource
{
    public $attributes = ['content', 'is_flagged'];

    public $relationships = ['user' => UserResource::class];
}
