<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Names no API resource, and spreads the raw models its parent's `toArray()` lists beside a key. */
class F38SpreadLeafCollection extends StubGizmoCollection
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'extra' => 1];
    }
}
