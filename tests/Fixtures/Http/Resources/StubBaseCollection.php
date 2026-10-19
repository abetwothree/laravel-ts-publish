<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** A body-less collection the stacked stub collections extend. */
class StubBaseCollection extends ResourceCollection
{
    public $collects = StubGadgetResource::class;
}
