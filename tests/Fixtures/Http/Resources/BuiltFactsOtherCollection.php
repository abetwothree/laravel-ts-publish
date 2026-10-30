<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BuiltFactsBaseCollection's `toArray()` that collects BuiltFactsOtherItemResource, which
 * the built instance maps into BuiltFactsItemResource, and re-indexes numeric keys.
 */
class BuiltFactsOtherCollection extends BuiltFactsBaseCollection
{
    public $collects = BuiltFactsOtherItemResource::class;

    public $preserveKeys = false;
}
