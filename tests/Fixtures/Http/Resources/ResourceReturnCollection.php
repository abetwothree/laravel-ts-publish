<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Returns `$this->resource`, the items it collects. */
class ResourceReturnCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return mixed */
    public function toArray(Request $request)
    {
        return $this->resource;
    }
}
