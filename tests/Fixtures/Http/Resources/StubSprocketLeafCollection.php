<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Names no API resource, and inherits a parent's own `toArray()` reading `$this->resource`. */
class StubSprocketLeafCollection extends StubSprocketCollection {}
