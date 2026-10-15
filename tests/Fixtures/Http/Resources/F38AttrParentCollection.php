<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A body-less collection naming its API resource by `#[Collects]`, which a subclass does not inherit. */
#[Collects(StubGadgetResource::class)]
class F38AttrParentCollection extends ResourceCollection {}
