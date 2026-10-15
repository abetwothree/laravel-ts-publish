<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Names no API resource over a `#[Collects]` parent, keeping its keys by its own `$preserveKeys`. */
class F38PropKeyedLeafCollection extends F38AttrParentCollection
{
    public $preserveKeys = true;
}
