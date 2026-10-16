<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A named collection of JSON:API resources: ResourceCollection's toArray() sends each item's resolve(), its resource
 * object under `data`.
 */
class PostCollection extends ResourceCollection
{
    public $collects = PostResource::class;
}
