<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Models\Post;

/**
 * A plain API resource spreading a JSON:API resource's resolve(), which holds one key, `data`. Laravel 12.45 and
 * later.
 *
 * @mixin Post
 */
class ResolveSpreadResource extends JsonResource
{
    /**
     * The post's id beside the JSON:API resource's response body.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            ...PostResource::make($this->resource)->resolve($request),
        ];
    }
}
