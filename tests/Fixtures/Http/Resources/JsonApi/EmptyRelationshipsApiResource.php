<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose `$relationships` names none, so no include can add to `included`. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class EmptyRelationshipsApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    public $relationships = [];
}
