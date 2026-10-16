<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Names no API resource over a `#[Collects]` parent, under its own wrap key. */
class F38AttrRowsLeafCollection extends F38AttrParentCollection
{
    public static $wrap = 'rows';
}
