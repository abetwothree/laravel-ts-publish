<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Keeps the stub over a `#[Collects]` parent, naming no API resource itself. */
class StubAttrLeafCollection extends F38AttrParentCollection
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
