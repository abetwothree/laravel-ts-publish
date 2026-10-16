<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Keeps the stub, with a `#[TsCasts]` on it. */
class MethodCastStubCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<int|string, mixed> */
    #[TsCasts(['meta' => 'string'])]
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
