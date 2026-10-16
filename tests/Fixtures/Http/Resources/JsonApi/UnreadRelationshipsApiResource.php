<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toRelationships() returns a variable, which names no relationship the package can read.
 * Laravel 12.45 and later.
 *
 * @mixin Post
 */
class UnreadRelationshipsApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    /**
     * The relationships an include can send.
     *
     * @return list<string>
     */
    public function toRelationships(Request $request): array
    {
        $relationships = ['author'];

        return $relationships;
    }
}
