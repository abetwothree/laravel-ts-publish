<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toArray() lists attribute names, which Laravel reads off the model. Laravel 12.45 and
 * later.
 *
 * @mixin Post
 */
class KeylessToArrayApiResource extends JsonApiResource
{
    /**
     * The attribute names toAttributes() falls back to.
     *
     * @return list<string>
     */
    public function toArray(Request $request): array
    {
        return ['title', 'content'];
    }
}
