<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BuiltSortsBaseCollection's `toArray()` that collects BuiltFactsOtherItemResource and
 * preserves keys.
 */
class BuiltSortsCollection extends BuiltSortsBaseCollection
{
    public $collects = BuiltFactsOtherItemResource::class;

    public $preserveKeys = true;
}
