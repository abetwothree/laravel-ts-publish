<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose empty `$relationships` default a method replaces, so an include may still reach one.
 * Laravel 12.45 and later.
 *
 * @mixin Post
 */
class WrittenRelationshipsApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    public $relationships = [];

    /**
     * Send the author relationship on an include.
     */
    public function withAuthor(): static
    {
        $this->relationships = ['author'];

        return $this;
    }
}
