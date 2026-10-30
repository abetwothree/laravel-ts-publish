<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only runtime class of BuiltFactsPostsBase's `toArray()` that collects BuiltFactsOtherItemResource. */
class BuiltFactsPostsOtherCollection extends BuiltFactsPostsBase
{
    public $collects = BuiltFactsOtherItemResource::class;
}
