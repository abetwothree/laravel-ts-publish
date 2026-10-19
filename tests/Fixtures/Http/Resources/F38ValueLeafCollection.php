<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Names no API resource, and sends the raw models its parent's `toArray()` lists under a key. */
class F38ValueLeafCollection extends StubGizmoCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['items' => parent::toArray($request)];
    }
}
