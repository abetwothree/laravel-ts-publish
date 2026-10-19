<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource with no `$attributes` whose toArray() spreads parent::toAttributes(), which calls toArray()
 * again, so Laravel never returns. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class LoopingToArrayApiResource extends JsonApiResource
{
    /**
     * A key beside what JsonApiResource's toAttributes() returns, which is this method again.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['extra' => 1, ...parent::toAttributes($request)];
    }
}
