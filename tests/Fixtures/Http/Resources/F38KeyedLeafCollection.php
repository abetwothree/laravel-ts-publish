<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Attributes\PreserveKeys;

/** Names no API resource over a `#[Collects]` parent, keeping its keys by its own `#[PreserveKeys]`. */
#[PreserveKeys]
class F38KeyedLeafCollection extends F38AttrParentCollection {}
