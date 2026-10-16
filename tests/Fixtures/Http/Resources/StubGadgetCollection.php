<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Keeps the `make:resource --collection` stub, collecting `StubGadgetResource` by its name. */
class StubGadgetCollection extends ResourceCollection
{
    /** @return array<int|string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
