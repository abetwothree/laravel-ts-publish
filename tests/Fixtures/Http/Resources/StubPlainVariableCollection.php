<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Returns `parent::toArray()` through a variable nothing else writes. */
class StubPlainVariableCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        $items = parent::toArray($request);

        return $items;
    }
}
