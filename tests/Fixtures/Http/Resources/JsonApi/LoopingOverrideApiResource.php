<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toAttributes() override spreads parent::toAttributes(), which reads toArray(), which
 * spreads the override again, so Laravel never returns. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class LoopingOverrideApiResource extends JsonApiResource
{
    /**
     * A key beside what JsonApiResource's toAttributes() returns, which reaches toArray().
     *
     * @return array<int|string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return ['headline' => $this->title, ...parent::toAttributes($request)];
    }

    /**
     * The override's keys, with one key more.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->toAttributes($request), 'more' => 1];
    }
}
