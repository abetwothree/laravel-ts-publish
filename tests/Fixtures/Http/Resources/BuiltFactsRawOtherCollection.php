<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of BuiltFactsRawBase's `toArray()` that collects BuiltFactsOtherItemResource. */
class BuiltFactsRawOtherCollection extends BuiltFactsRawBase
{
    public $collects = BuiltFactsOtherItemResource::class;
}
