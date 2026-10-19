<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Collects `StubWidgetResource` by its name, and sends keys beside the list in its own `toArray()`. */
class StubWidgetCollection extends ResourceCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request)
    {
        return ['data' => $this->collection, 'count' => $this->count()];
    }
}
