<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\PageItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose walked attributes spread a list of names beside a resource's keys under a nested key.
 * Laravel 12.45 and later.
 *
 * @mixin Post
 */
class NestedAppendedNamesApiResource extends JsonApiResource
{
    /**
     * The title, and a `row` that sends the appended name as a value beside the item resource's keys.
     *
     * @return array<array-key, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $names = [];
        $names[] = 'content';
        $attrs = ['title'];
        $attrs['row'] = [...$names, ...PageItemResource::make($this->resource)->resolve($request)];

        return $attrs;
    }
}
