<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** A body-less collection that collects nothing and sends no wrap key, so Laravel sends the raw items bare. */
class RawFlatCollection extends ResourceCollection
{
    /** @var string|null */
    public static $wrap = null;
}
