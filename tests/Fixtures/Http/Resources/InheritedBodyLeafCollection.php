<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** Inherits `InheritedBodyParentCollection`'s own `toArray()`, naming an API resource of its own. */
class InheritedBodyLeafCollection extends InheritedBodyParentCollection
{
    public $collects = StubGizmoResource::class;
}
