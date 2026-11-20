<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Workbench\App\Models\Post;

/**
 * GH-157's escape hatch: the keys publish flat, and a union written by hand is intersected with them, so `is_pinned`
 * still narrows `placement`.
 *
 * @mixin Post
 */
#[TsResource(discriminated: false)]
#[TsIntersect('PostPlacementVariants', '@/types/placement')]
class IntersectedPostResource extends PinnedPostResource {}
