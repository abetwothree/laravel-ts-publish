<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BuiltFactsBaseCollection's `toArray()` that collects BuiltFactsSubItemResource, which
 * the built instance keeps as it is, and re-indexes numeric keys.
 */
class BuiltFactsSubCollection extends BuiltFactsBaseCollection
{
    public $collects = BuiltFactsSubItemResource::class;

    public $preserveKeys = false;
}
