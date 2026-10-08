<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TraitBodies\ShapesCollectionLabel;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\HandoverResource;

/**
 * Takes its toArray() from a trait in another file and collects a resource, so its delegation would publish `data`.
 */
class TraitShapedCollection extends ResourceCollection
{
    use ShapesCollectionLabel;

    public $collects = HandoverResource::class;
}
