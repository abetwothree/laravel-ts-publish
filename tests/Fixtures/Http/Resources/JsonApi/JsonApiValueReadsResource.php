<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\JsonApi\PostResource;
use Workbench\App\Http\Resources\JsonApi\ToArrayPostResource;
use Workbench\App\Models\Post;

/**
 * A plain API resource reading JSON:API resources' methods as values: toArray() returns what the method does, and
 * resolve() the resource object under `data`. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class JsonApiValueReadsResource extends JsonResource
{
    /**
     * Each read as a key.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'declared' => ToArrayPostResource::make($this->resource)->toArray($request),
            'made' => PostResource::make($this->resource)->resolve($request),
            'built' => new PostResource($this->resource)->resolve($request),
        ];
    }
}
