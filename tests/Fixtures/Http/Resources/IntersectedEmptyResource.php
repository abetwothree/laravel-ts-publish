<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Workbench\App\Http\Resources\EmptyResource;

/**
 * A test-only resource with no key that publishes its intersection alone.
 */
#[TsIntersect('PostPlacementVariants', '@/types/placement')]
class IntersectedEmptyResource extends EmptyResource {}
