<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Keeps the `make:resource --collection` stub, collecting its API resource by `#[Collects]`. */
#[Collects(StubGadgetResource::class)]
class StubAttributeCollection extends ResourceCollection
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
