<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Returns `$this->resource`, the items it collects. Laravel sends them as it sends a body-less
 * collection's: `{ data: PostResource[] }`.
 */
class PostResourceReturnCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = PostResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return mixed
     */
    public function toArray(Request $request)
    {
        return $this->resource;
    }
}
