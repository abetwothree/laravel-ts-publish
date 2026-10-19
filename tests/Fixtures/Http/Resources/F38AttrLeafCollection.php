<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Names no API resource over a `#[Collects]` parent, so Laravel collects raw models. */
class F38AttrLeafCollection extends F38AttrParentCollection {}
