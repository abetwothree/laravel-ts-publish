<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** A body-less collection keeping its keys by `#[PreserveKeys]`, which a subclass does not inherit. */
#[Collects(StubGadgetResource::class)]
#[PreserveKeys]
class F38KeyedParentCollection extends ResourceCollection {}
