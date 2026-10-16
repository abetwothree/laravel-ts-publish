<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A body-less `#[Collects]` collection under `$wrap = null`. */
#[Collects(StubGadgetResource::class)]
class F38FlatParentCollection extends ResourceCollection
{
    public static $wrap = null;
}
