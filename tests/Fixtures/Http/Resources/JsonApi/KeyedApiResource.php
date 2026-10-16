<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource that preserves keys, so its collection() sends its items as an object keyed like the payload.
 * Laravel 12.45 and later.
 *
 * @mixin Post
 */
class KeyedApiResource extends JsonApiResource
{
    public $preserveKeys = true;

    public $attributes = ['title'];
}
