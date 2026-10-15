<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Inherits `SortedTagsResource`'s `toArray()`, keeping the keys it re-indexes. */
class KeyedSortedTagsResource extends SortedTagsResource
{
    public $preserveKeys = true;
}
