<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of BuiltFactsBaseCollection's `toArray()` that re-indexes numeric keys. */
class BuiltFactsCollection extends BuiltFactsBaseCollection
{
    public $preserveKeys = false;
}
