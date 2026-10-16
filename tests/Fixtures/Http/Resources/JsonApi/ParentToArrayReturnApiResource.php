<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toArray() returns parent::toArray() whole, as `make:resource` writes it. Laravel 12.45 and
 * later.
 *
 * @mixin Post
 */
class ParentToArrayReturnApiResource extends JsonApiResource
{
    /**
     * The model's own array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
