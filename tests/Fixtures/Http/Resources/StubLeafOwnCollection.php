<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Keeps the stub over a body-less parent, naming an API resource of its own. */
class StubLeafOwnCollection extends StubBaseCollection
{
    public $collects = StubGizmoResource::class;

    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
