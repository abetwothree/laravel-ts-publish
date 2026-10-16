<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** A body-less collection that collects nothing and keeps its source keys. */
class RawKeyedCollection extends ResourceCollection
{
    public $preserveKeys = true;
}
