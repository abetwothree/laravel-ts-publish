<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Keeps the stub over a body-less parent, collecting by the `$collects` it inherits. */
class StubLeafInheritCollection extends StubBaseCollection
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
