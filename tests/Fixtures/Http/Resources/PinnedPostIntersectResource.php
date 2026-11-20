<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that publishes its arms and an intersection.
 *
 * @mixin Post
 */
#[TsIntersect('PostPlacementVariants', '@/types/placement')]
class PinnedPostIntersectResource extends PinnedPostResource {}
