<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Returns `parent::toArray()` first, beside a return the sweep cannot read, so the first return is read. */
class StubFirstReturnCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $request->boolean('ids')) {
            return parent::toArray($request);
        }

        return $this->collection->pluck('id')->all();
    }
}
