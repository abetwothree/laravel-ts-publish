<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource sending links and meta through the defaults toLinks() and toMeta() return.
 *
 * @mixin Post
 */
class DefaultLinksPostResource extends JsonApiResource
{
    public $attributes = ['title'];

    protected array $jsonApiLinks = ['describedby' => 'https://example.test/schemas/posts'];

    protected array $jsonApiMeta = ['version' => 2, 'stable' => true];
}
