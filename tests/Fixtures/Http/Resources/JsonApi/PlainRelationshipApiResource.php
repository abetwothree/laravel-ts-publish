<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose relationships each build a plain API resource, one by its resolver and one by the related
 * model's guess, for which Laravel throws on an include. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class PlainRelationshipApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    public $relationships = ['author' => UserResource::class, 'comments'];
}
