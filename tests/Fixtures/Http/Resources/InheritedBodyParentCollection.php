<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A collection whose own `toArray()` sends keys beside the list it collects. */
class InheritedBodyParentCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['data' => $this->collection, 'count' => $this->count()];
    }
}
