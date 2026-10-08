<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Workbench\App\Http\Resources\HandoverSummaryResource;

/**
 * Body-less and collecting its own resource under a parent whose toArray() a trait supplies, which still wins.
 */
class TraitShapedChildCollection extends TraitShapedCollection
{
    public $collects = HandoverSummaryResource::class;
}
