<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose typed `$relationships` has no default, which its constructor fills, so an include may still
 * reach one. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class TypedRelationshipsApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    /** @var list<string> */
    protected array $relationships;

    /**
     * Send the author relationship on an include.
     */
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);

        $this->relationships = ['author'];
    }
}
