<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A body-less collection over a stub, naming an API resource of its own. */
class StubChildOfStubCollection extends StubCollectsCollection
{
    public $collects = StubGizmoResource::class;
}
