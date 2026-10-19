<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Attributes\Collects;
use Workbench\App\Http\Resources\HandoverCollection;
use Workbench\App\Http\Resources\HandoverSummaryResource;

/**
 * Stacked on a body-less collection whose `$collects` it inherits: on Laravel 13 its own `#[Collects]` wins.
 */
#[Collects(HandoverSummaryResource::class)]
class StackedAttributeDigestCollection extends HandoverCollection {}
