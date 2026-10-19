<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A collection that collects nothing and keeps its source keys, so Laravel sends the raw items keyed. */
class PageRawKeyedCollection extends ResourceCollection
{
    public $preserveKeys = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['data' => $this->collection];
    }
}
