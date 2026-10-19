<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Returns `parent::toArray()` as one key's value. */
class StubValueCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['items' => parent::toArray($request)];
    }
}
