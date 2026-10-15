<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** Names no API resource, and returns the collected list over a parent whose own `toArray()` sends other keys. */
class F38ListLeafCollection extends StubWidgetCollection
{
    /** @return mixed */
    public function toArray(Request $request)
    {
        return $this->collection;
    }
}
